<?php

/**
 * Skills Way Vocational Institute - Enhanced Landing Page
 * Advanced Developer Edition with Fresh Animations
 * Fixed and Optimized Version
 */

require_once 'config/database.php';
require_once 'config/session.php';

// Initialize session
$session = new SessionManager();

// Check if user is logged in
$isLoggedIn = $session->isLoggedIn();
$userType = $session->get('user_type');
$fullName = $session->get('full_name');

// Only redirect logged-in users to their dashboard if they're trying to access home
if ($isLoggedIn && basename($_SERVER['PHP_SELF']) == 'index.php') {
    switch ($userType) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'teacher':
            header("Location: teacher/dashboard.php");
            break;
        case 'student':
            header("Location: student/dashboard.php");
            break;
    }
    exit;
}

// Initialize variables
$settings = [];
$db = null;

// Get institute settings from database with error handling
try {
    $db = db()->getConnection();
    $stmt = $db->query("SELECT setting_key, setting_value FROM institute_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Default settings if database fails
    error_log("Database settings error: " . $e->getMessage());
    $settings = [
        'institute_name' => 'Skills Way Vocational Institute',
        'institute_address' => 'Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur',
        'contact_phone' => '0307-0237356, 0331-3307365',
        'contact_email' => 'askillswaykpr@gmail.com',
        'operating_hours' => 'Monday–Saturday, 8:00 AM – 9:00 PM'
    ];
}

// Set the logo path correctly - using the Logo Circle.png from uploads/images
$logoPath = 'uploads/images/Logo Circle.png';

// Check if the file exists, otherwise use a fallback
if (!file_exists($logoPath) || !is_file($logoPath)) {
    // Try alternative paths
    $logoPath = 'uploads/lab/logo.png';
    if (!file_exists($logoPath) || !is_file($logoPath)) {
        $logoPath = 'https://via.placeholder.com/150x150/2c3e50/ffffff?text=SWVI';
    }
}

// Set default institute name if not in settings
$instituteName = $settings['institute_name'] ?? 'Skills Way Vocational Institute';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($instituteName); ?></title>

    <!-- Meta Tags for SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($instituteName); ?> - Premier vocational institute offering IT, Technical Skills, and Language Training courses with state-of-the-art facilities.">
    <meta name="keywords" content="vocational training, technical education, IT courses, skill development, Pakistan, Khanpur">
    <meta name="author" content="<?php echo htmlspecialchars($instituteName); ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($instituteName); ?>">
    <meta property="og:description" content="Empowering youth with industry-relevant skills through comprehensive vocational training programs.">
    <meta property="og:image" content="<?php echo htmlspecialchars($logoPath); ?>">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo htmlspecialchars($logoPath); ?>" type="image/png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --animation-timing: cubic-bezier(0.4, 0, 0.2, 1);
            --navttc-green: #1e824c;
            --psda-blue: #2c3e50;
            --pbte-purple: #8e44ad;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Preloader with new animation */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a252f 100%);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .logo-loading {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: url('<?php echo htmlspecialchars($logoPath); ?>') center/contain no-repeat;
            margin-bottom: 30px;
            animation: logoPulse 2s ease-in-out infinite;
        }

        @keyframes logoPulse {

            0%,
            100% {
                transform: scale(1);
                filter: drop-shadow(0 0 20px rgba(52, 152, 219, 0.5));
            }

            50% {
                transform: scale(1.1);
                filter: drop-shadow(0 0 30px rgba(52, 152, 219, 0.8));
            }
        }

        .loading-text {
            color: white;
            font-size: 1.2rem;
            font-weight: 500;
            margin-top: 20px;
            opacity: 0.8;
            position: relative;
        }

        .loading-text::after {
            content: '...';
            position: absolute;
            animation: dots 1.5s infinite;
        }

        @keyframes dots {

            0%,
            20% {
                content: '.';
            }

            40% {
                content: '..';
            }

            60%,
            100% {
                content: '...';
            }
        }

        /* Navigation Bar */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
        }

        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--primary-color) !important;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.5s ease;
            border: 2px solid var(--secondary-color);
        }

        .navbar-brand:hover img {
            transform: rotate(15deg) scale(1.1);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
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
        }

        /* Enhanced Hero Section with NEW Animations */
        .hero-section {
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.97) 0%, rgba(52, 152, 219, 0.95) 100%);
            color: white;
            padding: 180px 0 100px;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        /* NEW: Animated Background Elements */
        .hero-animated-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-shape {
            position: absolute;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border-radius: 50%;
            animation: floatShape 20s infinite linear;
        }

        @keyframes floatShape {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            25% {
                transform: translate(100px, -50px) rotate(90deg);
            }

            50% {
                transform: translate(0, -100px) rotate(180deg);
            }

            75% {
                transform: translate(-100px, -50px) rotate(270deg);
            }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        /* NEW: Hero Logo Animation */
        .hero-logo-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 40px;
            perspective: 1000px;
        }

        .hero-logo {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 8px solid rgba(255, 255, 255, 0.2);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.3),
                0 0 50px rgba(255, 255, 255, 0.2),
                inset 0 0 50px rgba(255, 255, 255, 0.1);
            animation: logoFloat 6s ease-in-out infinite, logoGlow 3s ease-in-out infinite alternate;
            position: relative;
            z-index: 2;
        }

        @keyframes logoFloat {

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        @keyframes logoGlow {
            0% {
                box-shadow:
                    0 0 0 1px rgba(255, 255, 255, 0.3),
                    0 0 50px rgba(255, 255, 255, 0.2),
                    inset 0 0 50px rgba(255, 255, 255, 0.1);
            }

            100% {
                box-shadow:
                    0 0 0 1px rgba(255, 255, 255, 0.4),
                    0 0 80px rgba(255, 255, 255, 0.4),
                    inset 0 0 50px rgba(255, 255, 255, 0.2);
            }
        }

        .logo-ring {
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border-radius: 50%;
            border: 3px dashed rgba(255, 255, 255, 0.5);
            animation: rotateRing 20s linear infinite;
        }

        @keyframes rotateRing {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* NEW: Text Gradient Animation with Glitch Effect */
        .text-gradient {
            background: linear-gradient(45deg, #3498db, #2ecc71, #e74c3c, #f39c12);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            background-size: 300% 300%;
            animation: gradientShift 8s ease infinite, textGlitch 5s infinite;
            position: relative;
        }

        @keyframes gradientShift {

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

            96% {
                text-shadow: none;
            }

            97% {
                text-shadow: 2px 0 red, -2px 0 cyan;
            }

            98% {
                text-shadow: none;
            }

            99% {
                text-shadow: 2px 0 red, -2px 0 cyan;
            }
        }

        /* NEW: Typewriter Text Animation */
        .typewriter {
            overflow: hidden;
            border-right: .15em solid var(--secondary-color);
            white-space: nowrap;
            margin: 0 auto;
            letter-spacing: .15em;
            animation:
                typing 3.5s steps(30, end) forwards,
                blink-caret .75s step-end infinite 3.5s;
        }

        @keyframes typing {
            from {
                width: 0
            }

            to {
                width: 100%
            }
        }

        @keyframes blink-caret {

            from,
            to {
                border-color: transparent
            }

            50% {
                border-color: var(--secondary-color);
            }
        }

        /* NEW: Staggered Animation for Hero Elements */
        .hero-element {
            opacity: 0;
            transform: translateY(30px);
            animation: heroReveal 1s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .hero-element:nth-child(1) {
            animation-delay: 0.5s;
        }

        .hero-element:nth-child(2) {
            animation-delay: 1s;
        }

        .hero-element:nth-child(3) {
            animation-delay: 1.5s;
        }

        .hero-element:nth-child(4) {
            animation-delay: 2s;
        }

        .hero-element:nth-child(5) {
            animation-delay: 2.5s;
        }

        @keyframes heroReveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* NEW: Button Animation */
        .btn-magnetic {
            position: relative;
            overflow: hidden;
            border: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 14px 35px !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
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

        /* NEW: Statistics Counter Animation */
        .stats-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 50px;
            margin: 60px auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 900px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            100% {
                transform: translateX(100%);
            }
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .stat-counter {
            font-size: 4rem;
            font-weight: 900;
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #fff, #e6f7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            line-height: 1;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            opacity: 0;
            transform: scale(0.5);
            animation: counterReveal 1s cubic-bezier(0.4, 0, 0.2, 1) forwards var(--counter-delay);
        }

        @keyframes counterReveal {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .stat-counter::after {
            content: '+';
            position: absolute;
            right: -25px;
            top: 10px;
            font-size: 2.5rem;
            color: rgba(255, 255, 255, 0.8);
            animation: pulsePlus 2s ease-in-out infinite;
        }

        @keyframes pulsePlus {

            0%,
            100% {
                opacity: 0.8;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.2);
            }
        }

        .stat-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            letter-spacing: 1px;
            text-transform: uppercase;
            position: relative;
            padding-top: 15px;
        }

        .stat-label::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: var(--gradient-secondary);
            border-radius: 2px;
            animation: lineExpand 2s ease-in-out infinite;
        }

        @keyframes lineExpand {

            0%,
            100% {
                width: 40px;
            }

            50% {
                width: 60px;
            }
        }

        /* NEW: Enhanced Picture Frame Animation - COMPLETELY NEW DESIGN */
        .image-frame-container {
            position: relative;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            transform: perspective(1000px) rotateX(15deg) rotateY(-15deg);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            transform-origin: center;
        }

        .image-frame-container.revealed {
            opacity: 1;
            transform: perspective(1000px) rotateX(0) rotateY(0);
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
            animation: borderGlow 3s ease-in-out infinite alternate;
        }

        @keyframes borderGlow {
            0% {
                opacity: 0.7;
                filter: brightness(1);
            }

            100% {
                opacity: 1;
                filter: brightness(1.2);
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
                    rgba(255, 255, 255, 0.1) 50%,
                    transparent 70%);
            transform: rotate(45deg);
            animation: shineMove 3s ease-in-out infinite;
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
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            transform: scale(1);
            filter: brightness(1);
        }

        .image-frame-container:hover img {
            transform: scale(1.05);
            filter: brightness(1.1);
        }

        .image-frame-container:hover {
            transform: perspective(1000px) rotateX(-5deg) rotateY(5deg);
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.3);
        }

        /* NEW: Floating Particle Effects */
        .particles-container {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: linear-gradient(135deg, var(--secondary-color), var(--success-color));
            border-radius: 50%;
            opacity: 0;
            animation: floatParticle 20s infinite linear;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }

            10% {
                opacity: 0.2;
            }

            90% {
                opacity: 0.2;
            }

            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }

        /* NEW: Section Title Animation */
        .section-title-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 40px;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            position: relative;
            z-index: 2;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient-secondary);
            border-radius: 2px;
            animation: titleLine 2s ease-in-out infinite alternate;
        }

        @keyframes titleLine {
            0% {
                width: 80px;
                opacity: 0.7;
            }

            100% {
                width: 120px;
                opacity: 1;
            }
        }

        /* NEW: Accreditation Cards Holographic Effect */
        .holographic-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .holographic-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg,
                    #3498db, #2ecc71, #e74c3c, #f39c12,
                    #3498db, #2ecc71, #e74c3c, #f39c12);
            background-size: 400% 400%;
            z-index: -1;
            border-radius: 22px;
            opacity: 0;
            transition: opacity 0.6s ease;
            animation: holographicMove 4s linear infinite;
        }

        @keyframes holographicMove {
            0% {
                background-position: 0% 50%;
            }

            100% {
                background-position: 400% 50%;
            }
        }

        .holographic-card:hover::before {
            opacity: 1;
        }

        .holographic-card:hover {
            transform: translateY(-15px) scale(1.03);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }

        /* Icon Circle - Added missing class */
        .icon-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .psda-bg {
            background: linear-gradient(135deg, var(--psda-blue), #3498db);
        }

        .navttc-bg {
            background: linear-gradient(135deg, var(--navttc-green), #2ecc71);
        }

        .pbte-bg {
            background: linear-gradient(135deg, var(--pbte-purple), #9b59b6);
        }

        /* NEW: Text Reveal Animation */
        .text-reveal {
            position: relative;
            overflow: hidden;
        }

        .text-reveal::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-color);
            transform: translateX(-100%);
            animation: textReveal 1.5s cubic-bezier(0.4, 0, 0.2, 1) forwards var(--reveal-delay);
        }

        @keyframes textReveal {
            to {
                transform: translateX(100%);
            }
        }

        /* Footer Enhancements */
        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a252f 100%);
            color: white;
            padding: 80px 0 40px;
            position: relative;
            overflow: hidden;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-section {
                padding: 140px 0 80px;
                min-height: 90vh;
            }

            .hero-logo-container {
                width: 120px;
                height: 120px;
            }

            .stat-counter {
                font-size: 3rem;
            }

            .stats-section {
                padding: 30px;
                margin: 40px auto;
            }

            .image-frame-container {
                transform: perspective(1000px) rotateX(5deg) rotateY(-5deg);
            }

            .section-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                padding: 120px 0 60px;
                min-height: 80vh;
            }

            .hero-logo-container {
                width: 100px;
                height: 100px;
            }

            .stat-counter {
                font-size: 2.5rem;
            }

            .btn-magnetic {
                padding: 12px 25px !important;
                font-size: 0.9rem;
            }

            .holographic-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Preloader -->
    <div id="preloader">
        <div class="logo-loading"></div>
        <div class="loading-text">Loading Skills Way Institute</div>
    </div>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="<?php echo htmlspecialchars($logoPath); ?>"
                    alt="<?php echo htmlspecialchars($instituteName); ?> Logo"
                    onerror="this.onerror=null; this.src='https://via.placeholder.com/45x45/2c3e50/ffffff?text=SWVI'">
                <span><?php echo htmlspecialchars($instituteName); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Navigation Menu in EXACT specified sequence -->
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link" href="BEAUTY_PAYLOR.php">Beauty Parlor</a></li>
                    <li class="nav-item"><a class="nav-link" href="Co-Founder.php">Co-Founders</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <!-- Enroll Now as a button for CTA -->
                    <li class="nav-item ms-2">
                        <a class="btn btn-success btn-magnetic" href="enrollment-form.php">
                            <i class="fas fa-user-graduate me-2"></i>Enroll Now
                        </a>
                    </li>

                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown ms-2">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($fullName); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($userType); ?>/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-2"><a class="btn btn-outline-primary btn-magnetic" href="login.php">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a></li>
                        <li class="nav-item ms-2"><a class="btn btn-primary btn-magnetic" href="register.php">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Enhanced Hero Section with NEW Animations -->
    <section class="hero-section">
        <!-- Animated Background -->
        <div class="hero-animated-bg" id="floatingShapes"></div>

        <!-- Particles Container -->
        <div class="particles-container" id="particlesContainer"></div>

        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-8 hero-content">
                    <!-- Hero Logo -->
                    <div class="hero-logo-container hero-element">
                        <div class="logo-ring"></div>
                        <img src="<?php echo htmlspecialchars($logoPath); ?>"
                            alt="Skills Way Logo"
                            class="hero-logo"
                            onerror="this.onerror=null; this.src='https://via.placeholder.com/150x150/2c3e50/ffffff?text=SWVI'">
                    </div>

                    <!-- Badge -->
                    <div class="hero-element">
                        <span class="badge bg-success bg-opacity-25 text-success border border-success rounded-pill px-4 py-2 mb-4">
                            <i class="fas fa-award me-2"></i> NAVTTC Certified Partner Institute
                        </span>
                    </div>

                    <!-- Main Title -->
                    <h1 class="display-3 fw-bold mb-4 hero-element">
                        <span class="text-gradient d-block">Empowering Skills</span>
                    </h1>

                    <!-- Typewriter Subtitle -->
                    <h2 class="display-6 mb-4 hero-element">
                        <div class="typewriter text-light">for Tomorrow's Pakistan</div>
                    </h2>

                    <!-- Description -->
                    <p class="lead mb-5 hero-element">
                        Join Pakistan's premier vocational institute offering industry-relevant courses in
                        <span class="text-gradient fw-bold">IT, Technical Skills, Language Training</span> with
                        state-of-the-art facilities and expert faculty guidance.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="d-flex flex-wrap justify-content-center gap-4 hero-element">
                        <a href="enrollment-form.php" class="btn btn-primary btn-lg btn-magnetic">
                            <i class="fas fa-user-graduate me-2"></i>Start Your Journey Today
                        </a>
                        <a href="courses.php" class="btn btn-outline-light btn-lg btn-magnetic">
                            <i class="fas fa-book me-2"></i>Explore All Courses
                        </a>
                    </div>

                    <!-- Statistics Section -->
                    <div class="stats-section hero-element">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <div class="stat-counter" data-count="5000">0</div>
                                    <div class="stat-label">Successful Graduates</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <div class="stat-counter" data-count="50">0</div>
                                    <div class="stat-label">Industry Courses</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <div class="stat-counter" data-count="100">0</div>
                                    <div class="stat-label">Practical Training</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Accreditation Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-title-wrapper">
                    <h2 class="section-title display-5">
                        Our <span class="text-gradient">Accreditations</span>
                    </h2>
                </div>
                <p class="lead text-muted">
                    Officially recognized and affiliated with leading educational bodies
                </p>
            </div>

            <div class="row g-4">
                <!-- PSDA Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="holographic-card">
                        <div class="text-center mb-4">
                            <div class="icon-circle psda-bg mb-3 mx-auto" style="width: 80px; height: 80px;">
                                <i class="fas fa-file-contract fa-2x text-white"></i>
                            </div>
                            <h4 class="fw-bold text-gradient">PSDA</h4>
                            <h5 class="text-muted">Punjab Skills Development Authority</h5>
                        </div>
                        <p class="text-center mb-0">Government of Punjab Recognized Institute</p>
                    </div>
                </div>

                <!-- NAVTTC Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="holographic-card">
                        <div class="text-center mb-4">
                            <div class="icon-circle navttc-bg mb-3 mx-auto" style="width: 80px; height: 80px;">
                                <i class="fas fa-award fa-2x text-white"></i>
                            </div>
                            <h4 class="fw-bold text-gradient">NAVTTC</h4>
                            <h5 class="text-muted">National Vocational and Technical Training Commission</h5>
                        </div>
                        <p class="text-center mb-0">Federal Government Certified Institute</p>
                    </div>
                </div>

                <!-- PBTE Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="holographic-card">
                        <div class="text-center mb-4">
                            <div class="icon-circle pbte-bg mb-3 mx-auto" style="width: 80px; height: 80px;">
                                <i class="fas fa-graduation-cap fa-2x text-white"></i>
                            </div>
                            <h4 class="fw-bold text-gradient">PBTE</h4>
                            <h5 class="text-muted">Punjab Board of Technical Education</h5>
                        </div>
                        <p class="text-center mb-0">Board Affiliated Technical Education Programs</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Labs Gallery with NEW Picture Frames -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-title-wrapper">
                    <h2 class="section-title display-5">State-of-the-Art Lab Facilities</h2>
                </div>
                <p class="lead">
                    <span class="text-gradient fw-bold">Experience hands-on learning</span> in our fully-equipped, modern lab environments
                </p>
            </div>

            <div class="row g-4">
                <?php
                $labs = [
                    ['file' => 'lab1.jpeg', 'title' => 'Advanced Computer Lab', 'desc' => 'Latest hardware and software'],
                    ['file' => 'lab2.jpeg', 'title' => 'Networking Lab', 'desc' => 'Practical networking training'],
                    ['file' => 'lab3.jpeg', 'title' => 'Technical Training Lab', 'desc' => 'Hands-on technical skills'],
                    ['file' => 'lab4.jpeg', 'title' => 'Language Lab', 'desc' => 'Modern language learning'],
                    ['file' => 'lab5.jpeg', 'title' => 'Research Center', 'desc' => 'Advanced R&D space'],
                    ['file' => 'lab6.jpeg', 'title' => 'Main Training Hall', 'desc' => 'Spacious learning environment']
                ];

                foreach ($labs as $index => $lab):
                    $labImage = 'uploads/lab/' . $lab['file'];
                    if (!file_exists($labImage) || !is_file($labImage)) {
                        $labImage = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80';
                    }
                ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="image-frame-container" style="height: 300px;">
                            <div class="frame-border"></div>
                            <div class="frame-shine"></div>
                            <img src="<?php echo $labImage; ?>"
                                alt="<?php echo htmlspecialchars($lab['title']); ?>"
                                class="img-fluid"
                                loading="lazy">
                            <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white p-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($lab['title']); ?></h6>
                                <p class="small mb-0 opacity-75"><?php echo htmlspecialchars($lab['desc']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Facilities Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-title-wrapper">
                    <h2 class="section-title display-5">
                        Comprehensive <span class="text-gradient">Learning Ecosystem</span>
                    </h2>
                </div>
                <p class="lead text-muted">
                    Everything you need for successful skill development
                </p>
            </div>

            <div class="row g-4">
                <?php
                $facilities = [
                    ['icon' => 'fas fa-laptop-code', 'title' => 'Modern Computer Labs', 'desc' => 'Latest hardware and software'],
                    ['icon' => 'fas fa-wifi', 'title' => 'High-Speed Internet', 'desc' => '24/7 fiber-optic connectivity'],
                    ['icon' => 'fas fa-bed', 'title' => 'Hostel Facilities', 'desc' => 'Secure accommodation'],
                    ['icon' => 'fas fa-book-reader', 'title' => 'Digital Library', 'desc' => 'Extensive technical resources'],
                    ['icon' => 'fas fa-chalkboard-teacher', 'title' => 'Expert Faculty', 'desc' => 'Industry-experienced instructors'],
                    ['icon' => 'fas fa-briefcase', 'title' => 'Career Support', 'desc' => 'Placement assistance']
                ];

                foreach ($facilities as $index => $facility):
                ?>
                    <div class="col-md-4">
                        <div class="holographic-card">
                            <div class="text-center mb-4">
                                <div class="text-gradient" style="font-size: 3rem;">
                                    <i class="<?php echo $facility['icon']; ?>"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-center mb-3"><?php echo htmlspecialchars($facility['title']); ?></h4>
                            <p class="text-muted text-center mb-0"><?php echo htmlspecialchars($facility['desc']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5" style="background: linear-gradient(135deg, var(--primary-color) 0%, #3a506b 100%); color: white;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h3 class="fw-bold mb-3">Ready to Transform Your Career?</h3>
                    <p class="lead mb-0">Join thousands of successful graduates and start your journey today.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="enrollment-form.php" class="btn btn-light btn-lg btn-magnetic text-dark">
                        <i class="fas fa-paper-plane me-2"></i>Apply Now
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
                        <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($instituteName); ?></h4>
                    </div>
                    <p class="mb-4 opacity-75"><?php echo htmlspecialchars($settings['institute_address'] ?? 'Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur'); ?></p>
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
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-light text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="about.php" class="text-light text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="courses.php" class="text-light text-decoration-none">Courses</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-light text-decoration-none">Contact</a></li>
                        <li class="mb-2"><a href="enrollment-form.php" class="text-light text-decoration-none">Enroll Now</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="mb-3">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-phone me-2 opacity-75"></i>
                            <span class="opacity-75"><?php echo htmlspecialchars($settings['contact_phone'] ?? '0307-0237356, 0331-3307365'); ?></span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2 opacity-75"></i>
                            <a href="mailto:<?php echo htmlspecialchars($settings['contact_email'] ?? 'askillswaykpr@gmail.com'); ?>" class="text-light text-decoration-none">
                                <?php echo htmlspecialchars($settings['contact_email'] ?? 'askillswaykpr@gmail.com'); ?>
                            </a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-clock me-2 opacity-75"></i>
                            <span class="opacity-75"><?php echo htmlspecialchars($settings['operating_hours'] ?? 'Monday–Saturday, 8:00 AM – 9:00 PM'); ?></span>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 mb-4">
                    <h5 class="mb-3">Newsletter</h5>
                    <p class="mb-3 opacity-75">Subscribe for updates on courses and opportunities.</p>
                    <form class="newsletter-form" id="newsletterForm">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your email" required aria-label="Email for newsletter">
                            <button type="submit" class="btn btn-primary" aria-label="Subscribe">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <hr class="my-4 opacity-25">

            <div class="text-center pt-3">
                <p class="mb-0 opacity-75">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($instituteName); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Create floating shapes
            function createFloatingShapes() {
                const container = $('#floatingShapes');
                const shapesCount = 8;

                for (let i = 0; i < shapesCount; i++) {
                    const shape = $('<div class="floating-shape"></div>');
                    const size = Math.random() * 60 + 20;
                    const left = Math.random() * 100;
                    const top = Math.random() * 100;
                    const delay = Math.random() * 20;
                    const duration = Math.random() * 30 + 20;

                    shape.css({
                        width: size + 'px',
                        height: size + 'px',
                        left: left + '%',
                        top: top + '%',
                        animationDelay: delay + 's',
                        animationDuration: duration + 's'
                    });

                    container.append(shape);
                }
            }

            // Create particles
            function createParticles() {
                const container = $('#particlesContainer');
                const particleCount = 30;

                for (let i = 0; i < particleCount; i++) {
                    const particle = $('<div class="particle"></div>');
                    const size = Math.random() * 15 + 5;
                    const left = Math.random() * 100;
                    const top = Math.random() * 100;
                    const delay = Math.random() * 20;
                    const duration = Math.random() * 20 + 10;

                    particle.css({
                        width: size + 'px',
                        height: size + 'px',
                        left: left + '%',
                        top: top + '%',
                        animationDelay: delay + 's',
                        animationDuration: duration + 's'
                    });

                    container.append(particle);
                }
            }

            // Hide preloader
            setTimeout(() => {
                $('#preloader').fadeOut(500);
            }, 2000);

            // Counter animation
            function animateCounter() {
                $('.stat-counter').each(function() {
                    const $this = $(this);
                    const countTo = parseInt($this.attr('data-count'));
                    const countDuration = 2500;

                    $({
                        countNum: 0
                    }).animate({
                        countNum: countTo
                    }, {
                        duration: countDuration,
                        easing: 'swing',
                        step: function() {
                            $this.text(Math.floor(this.countNum));
                        },
                        complete: function() {
                            $this.text(this.countNum);
                        }
                    });
                });
            }

            // Start counter animation after preloader
            setTimeout(() => {
                animateCounter();
            }, 2500);

            // Typewriter effect with dynamic text
            function startTypewriter() {
                const typingText = document.querySelector('.typewriter');
                if (!typingText) return;

                const texts = [
                    "for Tomorrow's Pakistan",
                    "for Career Success",
                    "for Industry Excellence",
                    "for Digital Pakistan"
                ];
                let currentText = 0;
                let charIndex = 0;
                let isDeleting = false;
                let typingSpeed = 100;

                function type() {
                    const currentString = texts[currentText];

                    if (isDeleting) {
                        typingText.textContent = currentString.substring(0, charIndex - 1);
                        charIndex--;
                        typingSpeed = 50;
                    } else {
                        typingText.textContent = currentString.substring(0, charIndex + 1);
                        charIndex++;
                        typingSpeed = 100;
                    }

                    if (!isDeleting && charIndex === currentString.length) {
                        typingSpeed = 1500;
                        isDeleting = true;
                    } else if (isDeleting && charIndex === 0) {
                        isDeleting = false;
                        currentText = (currentText + 1) % texts.length;
                        typingSpeed = 500;
                    }

                    setTimeout(type, typingSpeed);
                }

                // Start typing after initial animation
                setTimeout(type, 2000);
            }

            // Image frame reveal on scroll
            function revealImageFrames() {
                $('.image-frame-container').each(function() {
                    const element = $(this);
                    const position = element.offset().top;
                    const screenPosition = $(window).scrollTop() + $(window).height() * 0.8;

                    if (position < screenPosition) {
                        element.addClass('revealed');
                    }
                });
            }

            // Newsletter form submission
            $('#newsletterForm').on('submit', function(e) {
                e.preventDefault();
                const email = $(this).find('input[type="email"]').val().trim();
                const button = $(this).find('button');
                const originalHTML = button.html();

                if (validateEmail(email)) {
                    button.html('<i class="fas fa-spinner fa-spin"></i>');
                    button.prop('disabled', true);

                    // Simulate API call
                    setTimeout(() => {
                        button.html('<i class="fas fa-check"></i>');
                        button.removeClass('btn-primary').addClass('btn-success');

                        setTimeout(() => {
                            button.html(originalHTML);
                            button.removeClass('btn-success').addClass('btn-primary');
                            button.prop('disabled', false);
                            $(this).trigger('reset');

                            showNotification('Successfully subscribed to newsletter!', 'success');
                        }, 1500);
                    }, 1000);
                } else {
                    showNotification('Please enter a valid email address', 'error');
                }
            });

            // Email validation
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            // Notification function
            function showNotification(message, type) {
                const notification = document.createElement('div');
                notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.zIndex = '9999';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;

                document.body.appendChild(notification);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            }

            // Smooth scrolling for anchor links
            $('a[href^="#"]').on('click', function(e) {
                if (this.hash !== '' && $(this.hash).length) {
                    e.preventDefault();
                    const hash = this.hash;

                    $('html, body').animate({
                        scrollTop: $(hash).offset().top - 70
                    }, 800);
                }
            });

            // Navbar scroll effect
            $(window).on('scroll', function() {
                const navbar = $('.navbar');
                if ($(window).scrollTop() > 50) {
                    navbar.addClass('shadow');
                } else {
                    navbar.removeClass('shadow');
                }

                revealImageFrames();
            });

            // Image error handling
            $('img').on('error', function() {
                if (!$(this).attr('data-error-handled')) {
                    // Provide different fallback images based on context
                    if ($(this).hasClass('hero-logo')) {
                        $(this).attr('src', 'https://via.placeholder.com/150x150/2c3e50/ffffff?text=SWVI');
                    } else if ($(this).parent().hasClass('image-frame-container')) {
                        $(this).attr('src', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');
                    } else {
                        $(this).attr('src', 'https://via.placeholder.com/400x300/2c3e50/ffffff?text=Skills+Way');
                    }
                    $(this).attr('data-error-handled', 'true');
                }
            });

            // Initialize animations
            createFloatingShapes();
            createParticles();
            startTypewriter();
            revealImageFrames();

            // Check animations on load
            $(window).trigger('scroll');

            // Performance optimization: Reduce animation on mobile
            if (window.innerWidth < 768) {
                $('.floating-shape, .particle').css('animation', 'none');
            }
        });
    </script>
</body>

</html>