<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Co-Founders - Skills Way Vocational Institute</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Additional CSS for animations -->
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #4a90e2;
            --accent-color: #ff6b6b;
            --success-color: #25d366;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-accent: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --shadow-soft: 0 8px 30px rgba(0,0,0,0.08);
            --shadow-medium: 0 15px 50px rgba(0,0,0,0.12);
            --shadow-hard: 0 25px 80px rgba(0,0,0,0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Animated Background Particles - Optimized */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            opacity: 0.1;
            animation: floatParticle 20s infinite linear;
        }
        
        @keyframes floatParticle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0.1;
            }
            50% {
                opacity: 0.15;
            }
            100% {
                transform: translateY(-100px) rotate(720deg);
                opacity: 0.1;
            }
        }
        
        /* Navigation Bar */
        .navbar {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--primary-color) !important;
            font-size: 1.5rem;
            position: relative;
            padding-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
            border: 3px solid var(--secondary-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .navbar-brand:hover .logo-img {
            transform: rotate(15deg) scale(1.1);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }
        
        .nav-link {
            font-weight: 500;
            position: relative;
            padding: 0.5rem 1rem !important;
            margin: 0 0.2rem;
            transition: all 0.3s ease;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--gradient-secondary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(-50%);
        }
        
        .nav-link:hover::before,
        .nav-link.active::before {
            width: 80%;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #3a6bc2 100%);
            color: white;
            padding: 120px 0 100px;
            margin-bottom: 50px;
            position: relative;
            overflow: hidden;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
        }
        
        @media (max-width: 768px) {
            .hero-section {
                clip-path: polygon(0 0, 100% 0, 100% 95%, 0 100%);
            }
        }
        
        .hero-logo-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 30px;
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
            0%, 100% { 
                transform: translateY(0) rotate(0deg);
            }
            50% { 
                transform: translateY(-20px) rotate(5deg);
            }
        }
        
        .hero-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 3.5rem;
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.2rem;
            }
            .hero-logo-container {
                width: 120px;
                height: 120px;
            }
        }
        
        /* Statistics Section - Fixed */
        .stats-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin: 40px auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 900px;
            position: relative;
            overflow: hidden;
        }
        
        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            position: relative;
            z-index: 2;
        }
        
        .stat-counter {
            font-size: 4.5rem;
            font-weight: 900;
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #fff, #e6f7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            line-height: 1;
            text-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
        }
        
        @media (max-width: 768px) {
            .stat-counter {
                font-size: 3rem;
            }
            .stats-section {
                padding: 20px;
            }
        }
        
        /* Founder Card - Fixed image loading */
        .founder-card {
            background: white;
            border-radius: 25px;
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 40px;
            position: relative;
            opacity: 1;
            transform: translateY(0);
        }
        
        .founder-card.reveal:not(.active) {
            transform: translateY(60px);
            opacity: 0;
        }
        
        .founder-image-wrapper {
            position: relative;
            width: 240px;
            height: 240px;
            margin: 40px auto 30px;
            perspective: 1000px;
        }
        
        .founder-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid transparent;
            background: linear-gradient(white, white) padding-box,
                        var(--gradient-secondary) border-box;
            position: relative;
            z-index: 2;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            animation: imageFloat 6s ease-in-out infinite;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        /* Add fallback for missing images */
        .founder-image:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            z-index: -1;
        }
        
        .founder-name {
            color: var(--primary-color);
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .founder-image-wrapper {
                width: 200px;
                height: 200px;
            }
            .founder-name {
                font-size: 1.8rem;
            }
        }
        
        /* Contact Info - Fixed buttons */
        .contact-info {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 30px;
            border-radius: 20px;
            margin: 25px;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .contact-btn {
            background: var(--gradient-secondary);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-block;
            margin: 8px;
            position: relative;
            overflow: hidden;
            z-index: 1;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .whatsapp-btn {
            background: linear-gradient(135deg, var(--success-color) 0%, #128c7e 100%);
        }
        
        /* Expertise Badges - Fixed animation */
        .expertise-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            margin: 5px;
            display: inline-block;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 1;
            transform: translateY(0);
        }
        
        .founder-card .expertise-badge {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .founder-card.active .expertise-badge {
            opacity: 1;
            transform: translateY(0);
        }
        
        .founder-card.active .expertise-badge:nth-child(1) { transition-delay: 0.2s; }
        .founder-card.active .expertise-badge:nth-child(2) { transition-delay: 0.4s; }
        .founder-card.active .expertise-badge:nth-child(3) { transition-delay: 0.6s; }
        
        /* Scroll Animation - Fixed */
        .reveal {
            opacity: 0;
            transform: translateY(60px);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Reduce motion for users who prefer it */
        @media (prefers-reduced-motion: reduce) {
            .reveal,
            .particle,
            .founder-image,
            .hero-logo,
            .contact-btn,
            .expertise-badge {
                animation: none !important;
                transition: none !important;
            }
            
            .reveal {
                opacity: 1;
                transform: none;
            }
        }
        
        /* Image loading state */
        .image-loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Error state for images */
        .image-error {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        /* Custom cursor for interactive elements */
        .interactive {
            cursor: pointer;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Section Titles */
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 50px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--primary-color);
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient-secondary);
            border-radius: 2px;
        }
        
        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            color: white;
            padding: 80px 0 40px;
            position: relative;
            clip-path: polygon(0 10%, 100% 0, 100% 100%, 0 100%);
        }
        
        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Back to Top Button -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-chevron-up"></i>
    </div>

    <!-- Animated Background Particles -->
    <div class="particles" id="particles"></div>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="uploads/images/Logo Circle.png" alt="Skills Way Logo" class="logo-img" 
                     onerror="this.src='https://via.placeholder.com/50/2c5aa0/ffffff?text=SW'">
                Skills Way Vocational Institute
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link active" href="Co-Founder.php">Co-Founders</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="enrollment-form.php">Enroll Now</a></li>
                    <li class="nav-item"><a class="btn btn-outline-primary ms-2" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-primary ms-2" href="register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <div class="hero-logo-container reveal">
                        <div class="logo-ring"></div>
                        <img src="uploads/images/Logo Circle.png" alt="Skills Way Logo" class="hero-logo"
                             onerror="this.src='https://via.placeholder.com/150/2c5aa0/ffffff?text=SW'">
                    </div>
                    
                    <div class="text-center mb-5 reveal">
                        <h1 class="hero-title">Skills Way Vocational Institute</h1>
                        <p class="lead mb-4" style="opacity: 0.9;">Empowering Youth Through Skill Development</p>
                    </div>
                    
                    <!-- Statistics Section -->
                    <div class="stats-section reveal">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <div class="stat-counter" data-target="5">0</div>
                                    <div class="stat-label">Years Experience</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <div class="stat-counter" data-target="1000">0</div>
                                    <div class="stat-label">Students Trained</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <div class="stat-counter" data-target="15">0</div>
                                    <div class="stat-label">Courses Offered</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Co-Founders Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Meet Our Co-Founders</h2>
                <p class="text-muted">Visionary leaders dedicated to transforming vocational education in Pakistan</p>
            </div>
            
            <div class="row">
                <!-- Kashif Kamran Raza -->
                <div class="col-lg-6 mb-5">
                    <div class="founder-card reveal">
                        <div class="founder-image-wrapper">
                            <div class="image-frame"></div>
                            <div class="image-ornament"></div>
                            <img src="uploads/images/cofounder/Kashif Kamran Raza.jpeg" 
                                 alt="Kashif Kamran Raza" class="founder-image"
                                 onerror="this.src='https://via.placeholder.com/240/2c5aa0/ffffff?text=KKR'"
                                 loading="lazy">
                        </div>
                        
                        <div class="text-center px-4 pb-4">
                            <h3 class="founder-name">Kashif Kamran Raza</h3>
                            <p class="founder-title">Co-Founder & CEO</p>
                            
                            <div class="contact-info">
                                <p class="mb-3"><i class="fas fa-phone me-2"></i><strong class="h5">0320-4782003</strong></p>
                                <p class="text-muted mb-3">Visionary leader with extensive experience in vocational education and skill development. Passionate about empowering youth through quality training programs.</p>
                                
                                <div class="d-flex justify-content-center flex-wrap">
                                    <a href="tel:03204782003" class="contact-btn call-btn interactive">
                                        <i class="fas fa-phone me-2"></i>Call Now
                                    </a>
                                    <a href="https://wa.me/923204782003?text=Hello! I would like to know more about Skills Way Vocational Institute. I visited your Co-Founders page." 
                                       target="_blank" class="contact-btn whatsapp-btn interactive">
                                        <i class="fab fa-whatsapp me-2"></i>WhatsApp
                                    </a>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="text-primary mb-3"><i class="fas fa-star me-2"></i>Expertise Areas:</h6>
                                <div class="d-flex justify-content-center flex-wrap">
                                    <span class="expertise-badge">Strategic Planning</span>
                                    <span class="expertise-badge curriculum">Business Development</span>
                                    <span class="expertise-badge innovation">Educational Leadership</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- M.Salman Khan -->
                <div class="col-lg-6 mb-5">
                    <div class="founder-card reveal">
                        <div class="founder-image-wrapper">
                            <div class="image-frame"></div>
                            <div class="image-ornament"></div>
                            <img src="uploads/images/cofounder/M.Salman Khan.jpeg" 
                                 alt="M.Salman Khan" class="founder-image"
                                 onerror="this.src='https://via.placeholder.com/240/4a90e2/ffffff?text=MSK'"
                                 loading="lazy">
                        </div>
                        
                        <div class="text-center px-4 pb-4">
                            <h3 class="founder-name">M.Salman Khan</h3>
                            <p class="founder-title">Co-Founder & CTO</p>
                            
                            <div class="contact-info">
                                <p class="mb-3"><i class="fas fa-phone me-2"></i><strong class="h5">0328-7446034</strong></p>
                                <p class="text-muted mb-3">Technology expert and innovative educator focused on integrating modern teaching methodologies with industry requirements for optimal learning outcomes.</p>
                                
                                <div class="d-flex justify-content-center flex-wrap">
                                    <a href="tel:03287446034" class="contact-btn call-btn interactive">
                                        <i class="fas fa-phone me-2"></i>Call Now
                                    </a>
                                    <a href="https://wa.me/923287446034?text=Hello! I would like to know more about Skills Way Vocational Institute. I visited your Co-Founders page." 
                                       target="_blank" class="contact-btn whatsapp-btn interactive">
                                        <i class="fab fa-whatsapp me-2"></i>WhatsApp
                                    </a>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="text-primary mb-3"><i class="fas fa-star me-2"></i>Expertise Areas:</h6>
                                <div class="d-flex justify-content-center flex-wrap">
                                    <span class="expertise-badge tech">Technology Integration</span>
                                    <span class="expertise-badge curriculum">Curriculum Development</span>
                                    <span class="expertise-badge innovation">Innovation Management</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Vision Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">OUR VISION & MISSION</h2>
                <p class="text-muted">Transforming vocational education for a brighter future</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 mb-4">
                    <div class="vision-card reveal">
                        <img src="uploads/images/our_mission/BEFORE 2025.png" alt="Before 2025" 
                             class="img-fluid rounded shadow"
                             onerror="this.onerror=null; this.src='https://via.placeholder.com/800x600/2c5aa0/ffffff?text=BEFORE+2025'"
                             loading="lazy">
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="vision-card reveal">
                        <img src="uploads/images/our_mission/INSHALLAH AFTER 2030.png" alt="After 2030" 
                             class="img-fluid rounded shadow"
                             onerror="this.onerror=null; this.src='https://via.placeholder.com/800x600/4a90e2/ffffff?text=AFTER+2030'"
                             loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Official Registration</h2>
                <p class="text-muted">Government recognized vocational training institute</p>
            </div>
            <div class="row justify-content-center mt-4">
                <div class="col-lg-10">
                    <div class="registration-frame reveal">
                        <img src="uploads/images/our_mission/REGISTRATION.png" alt="Registration Certificate" 
                             class="img-fluid rounded shadow"
                             onerror="this.onerror=null; this.src='https://via.placeholder.com/1000x700/667eea/ffffff?text=REGISTRATION'"
                             loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-3 mb-4">
                    <div class="d-flex align-items-center">
                        <img src="uploads/images/Logo Circle.png" alt="Skills Way Logo" 
                             class="logo-img me-3" style="width: 60px; height: 60px;"
                             onerror="this.src='https://via.placeholder.com/60/2c5aa0/ffffff?text=SW'">
                        <div>
                            <h4 class="mb-1 fw-bold">Skills Way</h4>
                            <p class="mb-0 small">Vocational Institute</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 col-md-6 mb-4">
                    <h5 class="fw-bold mb-3">Contact Information</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i>Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i>0307-0237356, 0331-3307365</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i>askillswaykpr@gmail.com</li>
                        <li class="mb-2"><i class="fas fa-clock me-2"></i>Mon-Sat: 8:00 AM - 9:00 PM</li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="fw-bold mb-3">Follow Us</h5>
                    <div class="social-icons">
                        <a href="#" class="interactive"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="interactive"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="interactive"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="interactive"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="interactive"><i class="fab fa-linkedin"></i></a>
                    </div>
                    <p class="mt-3 small">Empowering Youth Through Skill Development</p>
                </div>
            </div>
            <hr class="bg-light my-4">
            <div class="text-center pt-3">
                <p>&copy; <?php echo date('Y'); ?> Skills Way Vocational Institute. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
    $(document).ready(function() {
        // Hide loading overlay
        $('#loadingOverlay').fadeOut(500);
        
        // Create animated background particles
        function createParticles() {
            const particlesContainer = $('#particles');
            const particleCount = window.innerWidth < 768 ? 25 : 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = $('<div class="particle"></div>');
                const size = Math.random() * 20 + 5;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const delay = Math.random() * 20;
                const duration = Math.random() * 20 + 10;
                
                particle.css({
                    width: size + 'px',
                    height: size + 'px',
                    left: posX + '%',
                    top: posY + '%',
                    animationDelay: delay + 's',
                    animationDuration: duration + 's'
                });
                
                particlesContainer.append(particle);
            }
        }
        
        // Scroll reveal animation
        function scrollReveal() {
            const reveals = $('.reveal');
            const windowHeight = $(window).height();
            const revealPoint = 100;
            
            reveals.each(function() {
                const revealTop = $(this).offset().top;
                
                if (revealTop < $(window).scrollTop() + windowHeight - revealPoint) {
                    $(this).addClass('active');
                }
            });
        }
        
        // Counter animation
        function animateCounters() {
            $('.stat-counter').each(function() {
                const $this = $(this);
                const target = parseInt($this.data('target'));
                
                if ($this.hasClass('active') && !$this.hasClass('counted')) {
                    $this.addClass('counted');
                    
                    $({ countNum: 0 }).animate({
                        countNum: target
                    }, {
                        duration: 2500,
                        easing: 'swing',
                        step: function(now) {
                            $this.text(Math.floor(now));
                        },
                        complete: function() {
                            $this.text(target);
                        }
                    });
                }
            });
        }
        
        // Back to top button
        function handleBackToTop() {
            const $backToTop = $('#backToTop');
            if ($(window).scrollTop() > 300) {
                $backToTop.addClass('visible');
            } else {
                $backToTop.removeClass('visible');
            }
        }
        
        // Image loading handler
        function handleImageLoading() {
            $('img').on('load', function() {
                $(this).removeClass('image-loading');
            }).on('error', function() {
                $(this).addClass('image-error');
                $(this).removeClass('image-loading');
            });
            
            // Add loading class to images
            $('img:not([src=""])').addClass('image-loading');
        }
        
        // Handle phone clicks
        $('a[href^="tel:"]').on('click', function(e) {
            const phone = $(this).attr('href').replace('tel:', '');
            console.log('Calling:', phone);
            // You can add analytics tracking here
        });
        
        // Handle WhatsApp clicks
        $('.whatsapp-btn').on('click', function(e) {
            console.log('Opening WhatsApp');
            // You can add analytics tracking here
        });
        
        // Smooth scrolling for anchor links
        $('a[href^="#"]:not([href="#"])').on('click', function(event) {
            const target = $(this.getAttribute('href'));
            if (target.length) {
                event.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 1000, 'easeInOutCubic');
            }
        });
        
        // Back to top functionality
        $('#backToTop').on('click', function() {
            $('html, body').animate({
                scrollTop: 0
            }, 800, 'easeInOutCubic');
        });
        
        // Ripple effect for buttons
        $('.interactive').on('click', function(e) {
            const $btn = $(this);
            const ripple = $('<span class="ripple"></span>');
            const rect = $btn[0].getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.css({
                width: size + 'px',
                height: size + 'px',
                left: x + 'px',
                top: y + 'px'
            });
            
            $btn.find('.ripple').remove();
            $btn.append(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
        
        // Navbar scroll effect
        $(window).on('scroll', function() {
            if ($(window).scrollTop() > 50) {
                $('.navbar').addClass('navbar-scrolled');
            } else {
                $('.navbar').removeClass('navbar-scrolled');
            }
            handleBackToTop();
        });
        
        // Initialize everything
        createParticles();
        handleImageLoading();
        scrollReveal();
        
        // Event listeners
        $(window).on('scroll', function() {
            scrollReveal();
            animateCounters();
        });
        
        $(window).on('resize', function() {
            $('#particles').empty();
            createParticles();
        });
        
        // Trigger animations on load
        setTimeout(() => {
            scrollReveal();
            animateCounters();
        }, 100);
        
        // Add CSS for navbar scroll effect
        $('<style>').text(`
            .navbar-scrolled {
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                background: rgba(255, 255, 255, 0.98) !important;
            }
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: rippleEffect 0.6s linear;
            }
            @keyframes rippleEffect {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `).appendTo('head');
        
        // Handle browser compatibility
        if (!('ontouchstart' in window)) {
            $('.founder-card, .contact-btn, .interactive').addClass('hover-enabled');
        }
        
        // Add hover effects for non-touch devices
        $('<style>').text(`
            @media (hover: hover) {
                .hover-enabled:hover {
                    transform: translateY(-5px);
                }
                .founder-card.hover-enabled:hover {
                    transform: translateY(-10px) scale(1.02);
                }
            }
        `).appendTo('head');
    });
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // Page is visible again, restart animations if needed
            $('.stat-counter:not(.counted)').removeClass('active');
            setTimeout(() => {
                scrollReveal();
                animateCounters();
            }, 100);
        }
    });
    </script>
</body>
</html>