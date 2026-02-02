<?php

/**
 * Courses Catalog Page - Enhanced with New Animations
 * Showing 7 Specific Courses with Images and Prices
 */

require_once 'config/database.php';
require_once 'config/session.php';

$session = new SessionManager();
$isLoggedIn = $session->isLoggedIn();
$userType = $session->get('user_type');
$fullName = $session->get('full_name');

// Initialize settings array with default values
$settings = [
    'institute_name' => 'Skills Way Vocational Institute'
];

// Get institute settings
try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("SELECT setting_key, setting_value FROM institute_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Use default settings if database query fails
    error_log("Database error: " . $e->getMessage());
}

// Set logo path - FIXED: Add proper path checking
$logoPath = 'uploads/images/Logo Circle.png';
if (!file_exists($logoPath)) {
    // Try alternative paths
    $altPaths = [
        './uploads/images/Logo Circle.png',
        'uploads/images/Logo-Circle.png',
        './uploads/images/logo circle.png'
    ];
    foreach ($altPaths as $altPath) {
        if (file_exists($altPath)) {
            $logoPath = $altPath;
            break;
        }
    }
    // If still not found, use placeholder
    if (!file_exists($logoPath)) {
        $logoPath = 'https://via.placeholder.com/45x45/2c3e50/ffffff?text=SWVI';
    }
}

// Get filter parameters
$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$duration = $_GET['duration'] ?? 'all';
$type = $_GET['type'] ?? 'all';

// MANUALLY CREATE THE 7 COURSES YOU SPECIFIED
$courses = [
    // Course 1: Web Designing
    [
        'course_id' => 1,
        'course_name' => 'Web Designing',
        'description' => 'Learn professional web design with HTML, CSS, JavaScript, Bootstrap, and responsive design principles. Create stunning websites.',
        'fee_amount' => 40000,
        'duration_months' => 3,
        'max_students' => 20,
        'category_name' => 'IT & Technology',
        'active_batches' => 2,
        'total_applications' => 15,
        'course_image' => 'uploads/images/courses/web designing.jpg',
        'is_free' => false
    ],

    // Course 2: Graphics Designing
    [
        'course_id' => 2,
        'course_name' => 'Graphics Designing',
        'description' => 'Master Adobe Photoshop, Illustrator, and CorelDRAW. Create professional logos, banners, posters, and digital artwork.',
        'fee_amount' => 30000,
        'duration_months' => 3,
        'max_students' => 18,
        'category_name' => 'Design & Creative',
        'active_batches' => 1,
        'total_applications' => 12,
        'course_image' => 'uploads/images/courses/graphic designing.jpg',
        'is_free' => false
    ],

    // Course 3: Computer Application
    [
        'course_id' => 3,
        'course_name' => 'Computer Application',
        'description' => 'Learn Microsoft Office (Word, Excel, PowerPoint), internet skills, email, and basic computer operations for professional use.',
        'fee_amount' => 30000,
        'duration_months' => 2,
        'max_students' => 25,
        'category_name' => 'IT & Technology',
        'active_batches' => 3,
        'total_applications' => 20,
        'course_image' => 'uploads/images/courses/computer application.jpg',
        'is_free' => false
    ],

    // Course 4: AutoCAD Professional
    [
        'course_id' => 4,
        'course_name' => 'AutoCAD Professional',
        'description' => '2D and 3D CAD design and modeling for architects, engineers, and designers. Master professional drafting techniques.',
        'fee_amount' => 40000,
        'duration_months' => 4,
        'max_students' => 15,
        'category_name' => 'Engineering',
        'active_batches' => 1,
        'total_applications' => 8,
        'course_image' => 'uploads/images/courses/Autocad.jpg',
        'is_free' => false
    ],

    // Course 5: YouTube Automation
    [
        'course_id' => 5,
        'course_name' => 'YouTube Automation',
        'description' => 'Learn to automate YouTube channels, video editing, SEO optimization, monetization strategies, and content management.',
        'fee_amount' => 30000,
        'duration_months' => 3,
        'max_students' => 20,
        'category_name' => 'Digital Marketing',
        'active_batches' => 2,
        'total_applications' => 10,
        'course_image' => 'uploads/images/courses/Youtube.jpg',
        'is_free' => false
    ],

    // Course 6: Freelancing
    [
        'course_id' => 6,
        'course_name' => 'Freelancing',
        'description' => 'Learn how to start and grow your freelancing career. Find clients, manage projects, pricing strategies, and online platforms.',
        'fee_amount' => 5000,
        'duration_months' => 1,
        'max_students' => 30,
        'category_name' => 'Business',
        'active_batches' => 4,
        'total_applications' => 25,
        'course_image' => 'uploads/images/courses/Freelancing.jpg',
        'is_free' => false
    ],

    // Course 7: English Language Course
    [
        'course_id' => 7,
        'course_name' => 'English Language Course',
        'description' => 'Business communication and writing skills. Improve speaking, listening, reading, and writing for professional settings.',
        'fee_amount' => 20000,
        'duration_months' => 3,
        'max_students' => 20,
        'category_name' => 'Language',
        'active_batches' => 2,
        'total_applications' => 18,
        'course_image' => 'uploads/images/courses/English Language.jpg',
        'is_free' => false
    ],

    // Additional Courses
    [
        'course_id' => 8,
        'course_name' => 'Video Editing',
        'description' => 'Professional video editing with Adobe Premiere Pro, After Effects, and DaVinci Resolve. Create stunning videos for social media and YouTube.',
        'fee_amount' => 20000,
        'duration_months' => 3,
        'max_students' => 15,
        'category_name' => 'Design & Creative',
        'active_batches' => 1,
        'total_applications' => 10,
        'course_image' => 'uploads/images/courses/Video editing.jpg',
        'is_free' => false
    ],

    [
        'course_id' => 9,
        'course_name' => 'Digital Marketing',
        'description' => 'SEO, Social Media Marketing, Google Ads. Learn complete digital marketing strategies for business growth.',
        'fee_amount' => 40000,
        'duration_months' => 4,
        'max_students' => 20,
        'category_name' => 'Digital Marketing',
        'active_batches' => 2,
        'total_applications' => 15,
        'course_image' => 'uploads/images/courses/Digital Markiting.jpg',
        'is_free' => false
    ],

    [
        'course_id' => 10,
        'course_name' => 'E-commerce/Shopify/Amazon',
        'description' => 'Learn to create and manage online stores with Shopify, Amazon, and other e-commerce platforms. Dropshipping and digital marketing included.',
        'fee_amount' => 25000,
        'duration_months' => 3,
        'max_students' => 18,
        'category_name' => 'Business',
        'active_batches' => 1,
        'total_applications' => 12,
        'course_image' => 'uploads/images/courses/Ecommerce.jpg',
        'is_free' => false
    ],

    [
        'course_id' => 11,
        'course_name' => 'Beautician Course',
        'description' => 'Professional beauty and makeup training. Learn skincare, makeup techniques, hair styling, and salon management.',
        'fee_amount' => 70000,
        'duration_months' => 4,
        'max_students' => 15,
        'category_name' => 'Beauty & Wellness',
        'active_batches' => 2,
        'total_applications' => 10,
        'course_image' => 'uploads/images/courses/Beautician.jpg',
        'is_free' => false
    ],

    [
        'course_id' => 12,
        'course_name' => 'Python Programming',
        'description' => 'Learn Python programming from basics to advanced. Web development, data science, automation, and machine learning concepts.',
        'fee_amount' => 30000,
        'duration_months' => 4,
        'max_students' => 20,
        'category_name' => 'IT & Technology',
        'active_batches' => 1,
        'total_applications' => 15,
        'course_image' => 'uploads/images/courses/Python programming.jpg',
        'is_free' => false
    ],

    [
        'course_id' => 13,
        'course_name' => 'Web Development',
        'description' => 'Full stack web development with HTML, CSS, JavaScript, PHP, MySQL, and modern frameworks. Build complete web applications.',
        'fee_amount' => 40000,
        'duration_months' => 6,
        'max_students' => 15,
        'category_name' => 'IT & Technology',
        'active_batches' => 1,
        'total_applications' => 12,
        'course_image' => 'uploads/images/courses/Web development.jpg',
        'is_free' => false
    ],

    [
        'course_id' => 14,
        'course_name' => 'Digital Forensics',
        'description' => 'Comprehensive training in digital investigation, data recovery, and cyber crime analysis. Learn to uncover evidence and secure digital assets.',
        'fee_amount' => 40000,
        'duration_months' => 6,
        'max_students' => 15,
        'category_name' => 'IT & Technology',
        'active_batches' => 1,
        'total_applications' => 12,
        'course_image' => 'uploads/images/courses/digital_fronsi.jpeg',
        'is_free' => false
    ]
];

// Apply filters if needed
if (!empty($search)) {
    $courses = array_filter($courses, function ($course) use ($search) {
        return stripos($course['course_name'], $search) !== false ||
            stripos($course['description'], $search) !== false;
    });
}

if ($category !== 'all') {
    $courses = array_filter($courses, function ($course) use ($category) {
        return $course['category_name'] === $category;
    });
}

if ($type !== 'all') {
    if ($type === 'free') {
        $courses = array_filter($courses, function ($course) {
            return $course['is_free'] === true;
        });
    } else {
        $courses = array_filter($courses, function ($course) {
            return $course['is_free'] === false;
        });
    }
}

// Get categories for filter (from our courses array)
$categories = array_unique(array_column($courses, 'category_name'));
sort($categories);

// Count courses by category for better display
$categoryCounts = [];
foreach ($courses as $course) {
    $catName = $course['category_name'];
    if (!isset($categoryCounts[$catName])) {
        $categoryCounts[$catName] = 0;
    }
    $categoryCounts[$catName]++;
}

// Fix image paths - ensure they exist or use fallback
foreach ($courses as &$course) {
    $imagePath = $course['course_image'];

    // Check if image exists, if not try alternative paths
    if (!file_exists($imagePath)) {
        // Try with ./ prefix
        $altPath = './' . $imagePath;
        if (file_exists($altPath)) {
            $course['course_image'] = $altPath;
        } else {
            // Try to find the file in different variations
            $baseName = basename($imagePath);
            $possiblePaths = [
                './uploads/images/courses/' . $baseName,
                'uploads/images/courses/' . strtolower($baseName),
                'uploads/images/courses/' . str_replace(' ', '-', $baseName),
                'uploads/images/courses/' . str_replace(' ', '_', $baseName),
                './uploads/images/courses/' . strtolower($baseName),
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $course['course_image'] = $path;
                    break;
                }
            }

            // If still not found, use placeholder
            if (!file_exists($course['course_image'])) {
                $course['course_image'] = 'uploads/images/courses/SKILLSDEVELOPMENTAUTHORITY.png';
                if (!file_exists($course['course_image'])) {
                    $course['course_image'] = 'https://via.placeholder.com/800x600/2c3e50/ffffff?text=' . urlencode(substr($course['course_name'], 0, 20));
                }
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
    <title>Courses - <?php echo htmlspecialchars($settings['institute_name'] ?? 'Skills Way Vocational Institute'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-accent: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-success: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --animation-timing: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            overflow-x: hidden;
        }

        /* Logo Animation - ENHANCED */
        .logo-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid var(--secondary-color);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
            animation: logoPulse 2s ease-in-out infinite alternate;
        }

        @keyframes logoPulse {
            0% {
                transform: scale(1);
                box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
            }

            100% {
                transform: scale(1.05);
                box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
            }
        }

        .logo-img:hover {
            transform: rotate(15deg) scale(1.1);
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.6);
            animation: none;
        }

        /* Navigation - ENHANCED */
        .navbar {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            animation: slideDown 0.8s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--primary-color) !important;
            display: flex;
            align-items: center;
        }

        .nav-link {
            font-weight: 500;
            padding: 8px 15px !important;
            margin: 0 3px;
            position: relative;
            transition: all 0.3s ease;
            border-radius: 6px;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: var(--gradient-secondary);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .nav-link:hover::before,
        .nav-link.active::before {
            width: 70%;
        }

        .nav-link:hover {
            color: var(--secondary-color) !important;
            background: rgba(52, 152, 219, 0.05);
            transform: translateY(-2px);
        }

        /* Enhanced Hero Section with NEW Text Animations */
        .hero-courses {
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.95) 0%, rgba(52, 152, 219, 0.9) 100%);
            color: white;
            padding: 140px 0 80px;
            position: relative;
            overflow: hidden;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* NEW: Animated Background Elements */
        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: floatElement 25s infinite linear;
        }

        @keyframes floatElement {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0.1;
            }

            25% {
                transform: translate(100px, -50px) rotate(90deg);
                opacity: 0.2;
            }

            50% {
                transform: translate(0, -100px) rotate(180deg);
                opacity: 0.15;
            }

            75% {
                transform: translate(-100px, -50px) rotate(270deg);
                opacity: 0.2;
            }
        }

        /* NEW: Text Gradient Animation with Glitch Effect */
        .text-gradient-animated {
            background: linear-gradient(45deg, #3498db, #2ecc71, #e74c3c, #f39c12);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            background-size: 300% 300%;
            animation: gradientFlow 8s ease infinite, textGlitch 8s infinite;
            position: relative;
        }

        @keyframes gradientFlow {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes textGlitch {

            0%,
            100% {
                text-shadow: none;
            }

            95% {
                text-shadow: none;
            }

            96% {
                text-shadow: 1px 0 red, -1px 0 cyan;
            }

            97% {
                text-shadow: none;
            }

            98% {
                text-shadow: 1px 0 red, -1px 0 cyan;
            }

            99% {
                text-shadow: none;
            }
        }

        /* NEW: Title Reveal Animation */
        .title-reveal {
            overflow: hidden;
            position: relative;
            opacity: 0;
            transform: translateY(30px);
            animation: titleReveal 1s var(--animation-timing) forwards var(--reveal-delay);
        }

        .title-reveal:nth-child(1) {
            --reveal-delay: 0.3s;
        }

        .title-reveal:nth-child(2) {
            --reveal-delay: 0.6s;
        }

        .title-reveal:nth-child(3) {
            --reveal-delay: 0.9s;
        }

        @keyframes titleReveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* NEW: Button Animation - ENHANCED */
        .btn-magnetic {
            position: relative;
            overflow: hidden;
            border: none;
            transition: all 0.4s var(--animation-timing);
            padding: 14px 35px !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-radius: 50px !important;
        }

        .btn-magnetic::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
            border-radius: 50%;
        }

        .btn-magnetic:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-magnetic:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        }

        /* NEW: Enhanced Picture Frame Animation */
        .image-frame-container {
            position: relative;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            transform: perspective(1000px) rotateX(10deg) rotateY(-10deg);
            transition: all 0.8s var(--animation-timing);
            opacity: 0;
            transform-origin: center;
            height: 200px;
        }

        .image-frame-container.revealed {
            opacity: 1;
            transform: perspective(1000px) rotateX(0) rotateY(0);
            animation: frameReveal 1s ease-out;
        }

        @keyframes frameReveal {
            0% {
                opacity: 0;
                transform: perspective(1000px) rotateX(30deg) rotateY(-30deg);
            }

            100% {
                opacity: 1;
                transform: perspective(1000px) rotateX(0) rotateY(0);
            }
        }

        .frame-border {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 4px solid transparent;
            background: var(--gradient-secondary) border-box;
            mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: destination-out;
            border-radius: 25px;
            animation: borderPulse 3s ease-in-out infinite alternate;
            z-index: 1;
        }

        @keyframes borderPulse {
            0% {
                opacity: 0.7;
                filter: brightness(1) drop-shadow(0 0 10px rgba(74, 144, 226, 0.5));
            }

            100% {
                opacity: 1;
                filter: brightness(1.2) drop-shadow(0 0 20px rgba(74, 144, 226, 0.8));
            }
        }

        .frame-shine {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg,
                    transparent 30%,
                    rgba(255, 255, 255, 0.15) 50%,
                    transparent 70%);
            transform: rotate(45deg);
            animation: shineMove 4s ease-in-out infinite;
            z-index: 2;
        }

        @keyframes shineMove {

            0%,
            100% {
                transform: rotate(45deg) translateX(-100%);
            }

            50% {
                transform: rotate(45deg) translateX(100%);
            }
        }

        .image-frame-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.6s var(--animation-timing);
            transform: scale(1);
            filter: brightness(1) grayscale(20%);
        }

        .image-frame-container:hover img {
            transform: scale(1.08);
            filter: brightness(1.1) grayscale(0%);
        }

        .image-frame-container:hover {
            transform: perspective(1000px) rotateX(-5deg) rotateY(5deg);
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.3);
        }

        /* NEW: Course Card Enhancement - ENHANCED */
        .course-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.6s var(--animation-timing);
            height: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            position: relative;
            background: white;
            opacity: 0;
            transform: translateY(30px);
            animation: cardEntrance 0.8s ease-out forwards;
            animation-delay: calc(var(--card-index) * 0.1s);
        }

        @keyframes cardEntrance {
            0% {
                opacity: 0;
                transform: translateY(50px) scale(0.9);
            }

            70% {
                transform: translateY(-10px) scale(1.02);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .course-card.revealed {
            opacity: 1;
            transform: translateY(0);
        }

        .course-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }

        .course-category {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--gradient-secondary);
            color: white;
            padding: 6px 18px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 3;
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
            transition: all 0.4s ease;
            animation: badgePulse 2s infinite alternate;
        }

        @keyframes badgePulse {
            0% {
                transform: scale(1);
                box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
            }

            100% {
                transform: scale(1.05);
                box-shadow: 0 8px 25px rgba(74, 144, 226, 0.4);
            }
        }

        .course-card:hover .course-category {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.4);
            animation: none;
        }

        /* NEW: Price Animation - ENHANCED */
        .course-price {
            font-size: 1.8rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            position: relative;
            display: inline-block;
            animation: priceGlow 3s ease-in-out infinite alternate;
        }

        @keyframes priceGlow {
            0% {
                text-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
                transform: scale(1);
            }

            100% {
                text-shadow: 0 0 30px rgba(102, 126, 234, 0.6);
                transform: scale(1.05);
            }
        }

        /* NEW: Stats Badge Animation */
        .stats-badge {
            display: inline-block;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-right: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            animation: statsAppear 0.5s ease-out;
        }

        @keyframes statsAppear {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stats-badge:hover {
            transform: translateY(-3px);
            background: var(--gradient-success);
            color: white;
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        /* NEW: Filter Sidebar Animation - ENHANCED */
        .filter-sidebar {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 20px;
            transition: all 0.4s ease;
            border: 2px solid transparent;
            background-clip: padding-box;
            animation: sidebarSlide 0.8s ease-out;
        }

        @keyframes sidebarSlide {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .filter-sidebar::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: var(--gradient-secondary);
            border-radius: 22px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .filter-sidebar:hover::before {
            opacity: 1;
        }

        .filter-sidebar:hover {
            transform: translateY(-5px) translateX(5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        /* NEW: Section Title Animation */
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            animation: titleAppear 0.8s ease-out;
        }

        @keyframes titleAppear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--gradient-secondary);
            border-radius: 2px;
            animation: titleLine 2s ease-in-out infinite alternate;
        }

        @keyframes titleLine {
            0% {
                width: 60px;
                opacity: 0.7;
            }

            100% {
                width: 90px;
                opacity: 1;
            }
        }

        /* NEW: Search Bar Animation */
        .search-animated {
            position: relative;
            overflow: hidden;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            animation: searchSlide 0.8s ease-out;
        }

        @keyframes searchSlide {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-animated:focus-within {
            box-shadow: 0 15px 40px rgba(52, 152, 219, 0.2);
            transform: translateY(-2px);
        }

        .search-animated .input-group-text {
            background: var(--gradient-secondary);
            border: none;
            color: white;
            border-radius: 50px 0 0 50px;
            transition: all 0.3s ease;
        }

        .search-animated:focus-within .input-group-text {
            transform: scale(1.05);
        }

        /* NEW: Pagination Animation */
        .page-item .page-link {
            border: none;
            border-radius: 10px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .page-item.active .page-link {
            background: var(--gradient-secondary);
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
            animation: pagePulse 2s infinite alternate;
        }

        @keyframes pagePulse {
            0% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1.15);
            }
        }

        /* NEW: Footer Animation - ENHANCED */
        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a252f 100%);
            color: white;
            padding: 80px 0 40px;
            position: relative;
            overflow: hidden;
            animation: footerAppear 1s ease-out;
        }

        @keyframes footerAppear {
            from {
                opacity: 0;
                transform: translateY(50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg,
                    var(--secondary-color),
                    var(--success-color),
                    var(--accent-color));
            animation: gradientMove 3s linear infinite;
        }

        @keyframes gradientMove {
            0% {
                background-position: 0% 50%;
            }

            100% {
                background-position: 100% 50%;
            }
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.8) !important;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            display: inline-block;
        }

        .footer-link::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: var(--gradient-accent);
            transition: width 0.3s ease;
        }

        .footer-link:hover::before {
            width: 100%;
        }

        .footer-link:hover {
            color: white !important;
            transform: translateX(5px);
        }

        /* Course Type Badge - ENHANCED */
        .course-type-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--gradient-accent);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 700;
            z-index: 3;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.3);
            animation: badgeFloat 2s ease-in-out infinite alternate;
        }

        @keyframes badgeFloat {
            0% {
                transform: translateY(0);
            }

            100% {
                transform: translateY(-5px);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-courses {
                padding: 120px 0 60px;
                clip-path: polygon(0 0, 100% 0, 100% 95%, 0 100%);
            }

            .image-frame-container {
                transform: perspective(1000px) rotateX(5deg) rotateY(-5deg);
            }

            .course-card {
                margin-bottom: 25px;
            }

            .filter-sidebar {
                margin-bottom: 30px;
                position: static;
                animation: none;
            }
        }

        @media (max-width: 576px) {
            .hero-courses {
                padding: 100px 0 40px;
            }

            .display-4 {
                font-size: 2.5rem;
            }

            .btn-magnetic {
                padding: 12px 25px !important;
                font-size: 0.9rem;
            }

            .stats-badge {
                margin-bottom: 5px;
            }

            .course-card {
                animation: cardEntranceMobile 0.8s ease-out forwards;
                animation-delay: calc(var(--card-index) * 0.1s);
            }

            @keyframes cardEntranceMobile {
                0% {
                    opacity: 0;
                    transform: translateX(-50px) scale(0.9);
                }

                100% {
                    opacity: 1;
                    transform: translateX(0) scale(1);
                }
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="<?php echo htmlspecialchars($logoPath); ?>"
                    alt="<?php echo htmlspecialchars($settings['institute_name'] ?? 'Skills Way Vocational Institute'); ?> Logo"
                    class="logo-img"
                    onerror="this.onerror=null; this.src='https://via.placeholder.com/45x45/2c3e50/ffffff?text=SWVI'">
                <span><?php echo htmlspecialchars($settings['institute_name'] ?? 'Skills Way Vocational Institute'); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link" href="BEAUTY_PAYLOR.php">Beauty Parlor</a></li>
                    <li class="nav-item"><a class="nav-link" href="Co-Founder.php">Co-Founders</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="enrollment-form.php">Enroll Now</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($fullName); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo $userType; ?>/dashboard.php">Dashboard</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="btn btn-outline-primary btn-magnetic ms-2" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-primary btn-magnetic ms-2" href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section with NEW Animations -->
    <section class="hero-courses">
        <!-- Animated Background -->
        <div class="hero-background" id="floatingElements"></div>

        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <!-- Animated Title -->
                    <h1 class="display-4 fw-bold mb-4 title-reveal">
                        <span class="text-gradient-animated">All Professional Courses</span>
                    </h1>

                    <!-- Animated Subtitle -->
                    <p class="lead mb-4 title-reveal">
                        Master high-demand skills with our comprehensive courses in Web Design, Graphics, Digital Marketing, AutoCAD, and more.
                    </p>

                    <!-- Animated Buttons -->
                    <div class="d-flex gap-3 flex-wrap title-reveal">
                        <a href="#courses" class="btn btn-primary btn-lg btn-magnetic">
                            <i class="fas fa-book me-2"></i>Browse All Courses
                        </a>
                        <a href="enrollment-form.php" class="btn btn-outline-light btn-lg btn-magnetic">
                            <i class="fas fa-user-graduate me-2"></i>Enroll Now
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 d-none d-lg-block">
                    <!-- Course Stats -->
                    <div class="card border-0 shadow-lg rounded-20" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
                        <div class="card-body text-center text-white">
                            <h3 class="fw-bold"><?php echo count($courses); ?>+</h3>
                            <p class="mb-0">Professional Courses</p>
                            <hr class="my-3 bg-white opacity-25">
                            <div class="row">
                                <div class="col-6">
                                    <h5 class="fw-bold">From</h5>
                                    <p class="mb-0">Rs. 5,000</p>
                                </div>
                                <div class="col-6">
                                    <h5 class="fw-bold">To</h5>
                                    <p class="mb-0">Rs. 40,000</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <small><i class="fas fa-certificate me-1"></i>Certificate Included</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search and Filters -->
    <section class="py-5 bg-light">
        <div class="container">
            <form method="GET" action="" class="mb-4">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="input-group search-animated">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text"
                                class="form-control"
                                name="search"
                                placeholder="Search courses by name or description..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                    (<?php echo $categoryCounts[$cat] ?? 0; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <select class="form-select" name="duration" onchange="this.form.submit()">
                            <option value="all" <?php echo $duration === 'all' ? 'selected' : ''; ?>>Any Duration</option>
                            <option value="short" <?php echo $duration === 'short' ? 'selected' : ''; ?>>Short (≤3 months)</option>
                            <option value="medium" <?php echo $duration === 'medium' ? 'selected' : ''; ?>>Medium (3-6 months)</option>
                            <option value="long" <?php echo $duration === 'long' ? 'selected' : ''; ?>>Long (>6 months)</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <select class="form-select" name="type" onchange="this.form.submit()">
                            <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="free" <?php echo $type === 'free' ? 'selected' : ''; ?>>Free Courses</option>
                            <option value="paid" <?php echo $type === 'paid' ? 'selected' : ''; ?>>Paid Courses</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-lg-10">
                        <button type="submit" class="btn btn-primary btn-magnetic">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        <a href="courses.php" class="btn btn-outline-secondary btn-magnetic">Clear Filters</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Courses Grid -->
    <section class="py-5" id="courses">
        <div class="container">
            <div class="row">
                <!-- Sidebar Filters -->
                <div class="col-lg-3 mb-4">
                    <div class="filter-sidebar">
                        <h5 class="mb-4 section-title"><i class="fas fa-filter me-2"></i>Course Categories</h5>

                        <div class="mb-4">
                            <h6 class="mb-3">Quick Categories</h6>
                            <div class="list-group list-group-flush">
                                <a href="?search=web" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    Web Development
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo count(array_filter($courses, function ($c) {
                                            return stripos($c['course_name'], 'web') !== false;
                                        })); ?>
                                    </span>
                                </a>
                                <a href="?search=graphic" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    Graphics Designing
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo count(array_filter($courses, function ($c) {
                                            return stripos($c['course_name'], 'graphic') !== false;
                                        })); ?>
                                    </span>
                                </a>
                                <a href="?search=digital" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    Digital Marketing
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo count(array_filter($courses, function ($c) {
                                            return stripos($c['course_name'], 'digital') !== false;
                                        })); ?>
                                    </span>
                                </a>
                                <a href="?search=autocad" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    AutoCAD/Sketchup
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo count(array_filter($courses, function ($c) {
                                            return stripos($c['course_name'], 'autocad') !== false;
                                        })); ?>
                                    </span>
                                </a>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="mb-3">All Categories</h6>
                            <div class="list-group list-group-flush">
                                <a href="?category=all<?php echo !empty($search) ? "&search=$search" : ''; ?><?php echo $duration !== 'all' ? "&duration=$duration" : ''; ?><?php echo $type !== 'all' ? "&type=$type" : ''; ?>"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $category === 'all' ? 'active' : ''; ?>">
                                    All Categories
                                    <span class="badge bg-primary rounded-pill"><?php echo count($courses); ?></span>
                                </a>
                                <?php foreach ($categories as $cat):
                                    $catCount = $categoryCounts[$cat] ?? 0;
                                ?>
                                    <a href="?category=<?php echo urlencode($cat); ?><?php echo !empty($search) ? "&search=$search" : ''; ?><?php echo $duration !== 'all' ? "&duration=$duration" : ''; ?><?php echo $type !== 'all' ? "&type=$type" : ''; ?>"
                                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $category === $cat ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                        <span class="badge bg-secondary rounded-pill"><?php echo $catCount; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="mb-3">Course Duration</h6>
                            <div class="list-group list-group-flush">
                                <a href="?duration=all<?php echo !empty($search) ? "&search=$search" : ''; ?><?php echo $category !== 'all' ? "&category=$category" : ''; ?><?php echo $type !== 'all' ? "&type=$type" : ''; ?>"
                                    class="list-group-item list-group-item-action <?php echo $duration === 'all' ? 'active' : ''; ?>">Any Duration</a>
                                <a href="?duration=short<?php echo !empty($search) ? "&search=$search" : ''; ?><?php echo $category !== 'all' ? "&category=$category" : ''; ?><?php echo $type !== 'all' ? "&type=$type" : ''; ?>"
                                    class="list-group-item list-group-item-action <?php echo $duration === 'short' ? 'active' : ''; ?>">Short (≤3 months)</a>
                                <a href="?duration=medium<?php echo !empty($search) ? "&search=$search" : ''; ?><?php echo $category !== 'all' ? "&category=$category" : ''; ?><?php echo $type !== 'all' ? "&type=$type" : ''; ?>"
                                    class="list-group-item list-group-item-action <?php echo $duration === 'medium' ? 'active' : ''; ?>">Medium (3-6 months)</a>
                                <a href="?duration=long<?php echo !empty($search) ? "&search=$search" : ''; ?><?php echo $category !== 'all' ? "&category=$category" : ''; ?><?php echo $type !== 'all' ? "&type=$type" : ''; ?>"
                                    class="list-group-item list-group-item-action <?php echo $duration === 'long' ? 'active' : ''; ?>">Long (>6 months)</a>
                            </div>
                        </div>

                        <div class="text-center">
                            <a href="enrollment-form.php" class="btn btn-primary btn-magnetic w-100">
                                <i class="fas fa-user-graduate me-2"></i>Enroll Now
                            </a>
                            <p class="text-muted mt-2 small">Need help choosing? <a href="contact.php" class="text-decoration-none">Contact our advisors</a></p>
                        </div>
                    </div>
                </div>

                <!-- Courses List -->
                <div class="col-lg-9">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="section-title">Available Courses (<?php echo count($courses); ?>)</h3>
                        <p class="mb-0 badge bg-primary rounded-pill"><?php echo count($courses); ?> courses found</p>
                    </div>

                    <?php if (empty($courses)): ?>
                        <div class="alert alert-info shadow-sm border-0 rounded-20">
                            <i class="fas fa-info-circle me-2"></i>No courses found matching your criteria. Try different filters.
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php
                            // Reset array index for animation
                            $courses = array_values($courses);
                            foreach ($courses as $index => $course):
                                // Determine course type for badge
                                $courseType = 'Professional';
                                if ($course['fee_amount'] <= 10000) {
                                    $courseType = 'Affordable';
                                } elseif ($course['fee_amount'] >= 35000) {
                                    $courseType = 'Premium';
                                }

                                // Check if image exists
                                $imagePath = $course['course_image'];
                                $imageExists = file_exists($imagePath);
                                $finalImage = $imageExists ? $imagePath : 'https://via.placeholder.com/800x600/2c3e50/ffffff?text=' . urlencode(substr($course['course_name'], 0, 20));
                            ?>
                                <div class="col-lg-6">
                                    <div class="card course-card" style="--card-index: <?php echo $index; ?>;">
                                        <div class="position-relative">
                                            <div class="image-frame-container">
                                                <div class="frame-border"></div>
                                                <div class="frame-shine"></div>
                                                <img src="<?php echo htmlspecialchars($finalImage); ?>"
                                                    class="card-img-top"
                                                    alt="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                    style="object-fit: cover; height: 200px;"
                                                    onerror="this.onerror=null; this.src='https://via.placeholder.com/800x600/2c3e50/ffffff?text=<?php echo urlencode(substr($course['course_name'], 0, 20)); ?>'">
                                            </div>
                                            <div class="course-category">
                                                <?php echo htmlspecialchars($course['category_name']); ?>
                                            </div>
                                            <div class="course-type-badge">
                                                <?php echo $courseType; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                            <p class="card-text text-muted">
                                                <?php echo htmlspecialchars($course['description']); ?>
                                            </p>

                                            <div class="mb-3">
                                                <span class="course-price">Rs. <?php echo number_format($course['fee_amount']); ?></span>
                                                <small class="text-success ms-2">
                                                    <i class="fas fa-certificate me-1"></i>Certificate Included
                                                </small>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2 mb-3">
                                                <span class="stats-badge">
                                                    <i class="fas fa-clock me-1"></i><?php echo $course['duration_months']; ?> months
                                                </span>
                                                <span class="stats-badge">
                                                    <i class="fas fa-users me-1"></i><?php echo $course['active_batches']; ?> batches
                                                </span>
                                                <span class="stats-badge">
                                                    <i class="fas fa-user-graduate me-1"></i><?php echo $course['total_applications']; ?> applications
                                                </span>
                                            </div>

                                            <?php if ($course['max_students']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-friends me-1"></i>Max <?php echo $course['max_students']; ?> students per batch
                                                    </small>
                                                </div>
                                            <?php endif; ?>

                                            <div class="d-grid gap-2">
                                                <a href="course-detail.php?id=<?php echo $course['course_id']; ?>"
                                                    class="btn btn-outline-primary btn-magnetic">
                                                    <i class="fas fa-info-circle me-2"></i>View Details
                                                </a>
                                                <a href="enrollment-form.php?course=<?php echo $course['course_id']; ?>"
                                                    class="btn btn-primary btn-magnetic">
                                                    <i class="fas fa-user-plus me-2"></i>Enroll Now
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($courses) > 12): ?>
                            <nav aria-label="Course pagination" class="mt-5">
                                <div class="text-center">
                                    <p class="text-muted">Showing all <?php echo count($courses); ?> courses</p>
                                </div>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-5 bg-gradient-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h3 class="fw-bold mb-3">Not Sure Which Course to Choose?</h3>
                    <p class="mb-0">Get free career counseling from our experts to find the perfect course for your goals.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="contact.php" class="btn btn-light btn-lg btn-magnetic">
                        <i class="fas fa-headset me-2"></i>Get Free Counseling
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="d-flex align-items-center mb-4">
                        <img src="<?php echo htmlspecialchars($logoPath); ?>"
                            alt="Logo"
                            height="45"
                            class="me-3 rounded-circle"
                            onerror="this.onerror=null; this.src='https://via.placeholder.com/45x45/2c3e50/ffffff?text=SWVI'">
                        <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($settings['institute_name'] ?? 'Skills Way Vocational Institute'); ?></h4>
                    </div>
                    <p class="mb-4 opacity-75">Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur</p>
                    <div class="social-icons d-flex gap-2">
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="footer-link">Home</a></li>
                        <li class="mb-2"><a href="about.php" class="footer-link">About Us</a></li>
                        <li class="mb-2"><a href="courses.php" class="footer-link">Courses</a></li>
                        <li class="mb-2"><a href="contact.php" class="footer-link">Contact</a></li>
                        <li class="mb-2"><a href="enrollment-form.php" class="footer-link">Enroll Now</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Need Help?</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-phone me-2 opacity-75"></i>0307-0237356, 0331-3307365</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2 opacity-75"></i>askillswaykpr@gmail.com</li>
                        <li class="mb-2"><i class="fas fa-clock me-2 opacity-75"></i>Mon-Sat: 8:00 AM - 9:00 PM</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Popular Courses with Prices</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="?search=web designing" class="footer-link d-flex justify-content-between">
                                <span>Web Designing</span>
                                <span class="badge bg-success">Rs. 40,000</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="?search=graphic designing" class="footer-link d-flex justify-content-between">
                                <span>Graphics Designing</span>
                                <span class="badge bg-success">Rs. 30,000</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="?search=autocad" class="footer-link d-flex justify-content-between">
                                <span>AutoCAD Professional</span>
                                <span class="badge bg-success">Rs. 40,000</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="?search=digital marketing" class="footer-link d-flex justify-content-between">
                                <span>Digital Marketing</span>
                                <span class="badge bg-success">Rs. 40,000</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="?search=beautician" class="footer-link d-flex justify-content-between">
                                <span>Beautician</span>
                                <span class="badge bg-success">Rs. 70,000</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <hr class="bg-light opacity-25">
            <div class="text-center pt-3">
                <p class="mb-0 opacity-75">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['institute_name'] ?? 'Skills Way Vocational Institute'); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create floating background elements
            function createFloatingElements() {
                const container = document.getElementById('floatingElements');
                if (!container) return;

                const elementsCount = 12;

                for (let i = 0; i < elementsCount; i++) {
                    const element = document.createElement('div');
                    element.className = 'floating-element';
                    const size = Math.random() * 40 + 20;
                    const left = Math.random() * 100;
                    const top = Math.random() * 100;
                    const delay = Math.random() * 20;
                    const duration = Math.random() * 30 + 20;

                    element.style.cssText = `
                        width: ${size}px;
                        height: ${size}px;
                        left: ${left}%;
                        top: ${top}%;
                        animation-delay: ${delay}s;
                        animation-duration: ${duration}s;
                    `;

                    container.appendChild(element);
                }
            }

            // Initialize all animations
            function initializeAnimations() {
                // Animate course cards on load
                $('.course-card').each(function(index) {
                    $(this).css({
                        'animation-delay': (index * 0.1) + 's'
                    });
                });

                // Animate image frames
                $('.image-frame-container').each(function(index) {
                    $(this).css({
                        'animation-delay': (index * 0.15) + 's'
                    });
                    $(this).addClass('revealed');
                });

                // Add hover effects
                $('.course-card').hover(
                    function() {
                        $(this).css('transform', 'translateY(-10px) scale(1.02)');
                    },
                    function() {
                        $(this).css('transform', 'translateY(0) scale(1)');
                    }
                );
            }

            // Auto-submit filters on change
            $('select[name="category"], select[name="duration"], select[name="type"]').on('change', function() {
                $(this).closest('form').submit();
            });

            // Initialize animations
            createFloatingElements();
            initializeAnimations();

            // Image error handling
            $('img').on('error', function() {
                if (!$(this).data('error-handled')) {
                    const altText = $(this).attr('alt') || 'Course Image';
                    $(this).attr('src', 'https://via.placeholder.com/800x600/2c3e50/ffffff?text=' + encodeURIComponent(altText.substring(0, 20)));
                    $(this).data('error-handled', true);
                }
            });

            // Smooth scrolling for anchor links
            $('a[href^="#"]').on('click', function(e) {
                e.preventDefault();
                const targetId = $(this).attr('href');
                if (targetId.length > 1) {
                    const targetElement = $(targetId);
                    if (targetElement.length) {
                        $('html, body').animate({
                            scrollTop: targetElement.offset().top - 80
                        }, 800);
                    }
                }
            });

            // Course card click tracking
            $('.course-card').on('click', function(e) {
                if (!$(e.target).closest('a').length) {
                    const courseLink = $(this).find('a[href*="course-detail"]');
                    if (courseLink.length) {
                        courseLink[0].click();
                    }
                }
            });

            // Animate elements on scroll
            function animateOnScroll() {
                $('.course-card, .image-frame-container').each(function() {
                    const elementTop = $(this).offset().top;
                    const windowHeight = $(window).height();
                    const scrollTop = $(window).scrollTop();

                    if (elementTop < scrollTop + windowHeight - 100) {
                        $(this).addClass('revealed');
                    }
                });
            }

            // Check on scroll
            $(window).on('scroll', animateOnScroll);

            // Initial check
            animateOnScroll();

            // Logo animation enhancement
            $('.logo-img').hover(
                function() {
                    $(this).css({
                        'transform': 'rotate(15deg) scale(1.1)',
                        'box-shadow': '0 10px 30px rgba(52, 152, 219, 0.6)'
                    });
                },
                function() {
                    $(this).css({
                        'transform': 'rotate(0) scale(1)',
                        'box-shadow': '0 5px 15px rgba(52, 152, 219, 0.2)'
                    });
                }
            );
        });

        // Global function for form submission
        window.submitFilterForm = function() {
            $('form').submit();
        };
    </script>
</body>

</html>