<?php
/**
 * Admin Dashboard Header - Professional Version
 * File: admin/includes/header.php
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true); // Prevent session fixation
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Check if user is authenticated and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login-test.php');
    exit();
}

// CSRF Token generation for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set timezone
date_default_timezone_set('Asia/Karachi');

// User data from session
$user = [
    'user_id' => $_SESSION['user_id'],
    'full_name' => htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'),
    'username' => htmlspecialchars($_SESSION['username'] ?? 'admin'),
    'email' => htmlspecialchars($_SESSION['email'] ?? ''),
    'last_login' => $_SESSION['last_login'] ?? null,
    'profile_image' => $_SESSION['profile_image'] ?? null
];

// Database connection and stats
$stats = [
    'total_students' => 0,
    'total_teachers' => 0,
    'total_courses' => 0,
    'pending_applications' => 0,
    'active_batches' => 0,
    'revenue_today' => 0,
    'revenue_month' => 0
];

$unreadNotifications = 0;
$recentActivities = [];
$systemStatus = 'normal';

try {
    require_once '../config/database.php';
    $db = (new Database())->getConnection();
    
    if ($db) {
        // Get comprehensive stats in a single query (more efficient)
        $statsQuery = "
            SELECT 
                (SELECT COUNT(*) FROM users WHERE user_type = 'student' AND account_status = 'active') as total_students,
                (SELECT COUNT(*) FROM users WHERE user_type = 'teacher' AND account_status = 'active') as total_teachers,
                (SELECT COUNT(*) FROM courses WHERE is_active = 1) as total_courses,
                (SELECT COUNT(*) FROM enrollment_applications WHERE application_status IN ('submitted', 'under_review')) as pending_applications,
                (SELECT COUNT(*) FROM batches WHERE status = 'ongoing' AND end_date >= CURDATE()) as active_batches,
                (SELECT COALESCE(SUM(payment_amount), 0) FROM enrollment_applications 
                 WHERE DATE(application_date) = CURDATE() AND payment_status = 'paid') as revenue_today,
                (SELECT COALESCE(SUM(payment_amount), 0) FROM enrollment_applications 
                 WHERE MONTH(application_date) = MONTH(CURDATE()) AND YEAR(application_date) = YEAR(CURDATE()) 
                 AND payment_status = 'paid') as revenue_month
        ";
        
        $stmt = $db->query($statsQuery);
        if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats = array_merge($stats, $row);
        }
        
        // Get unread notifications count
        $notifStmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $notifStmt->execute([$user['user_id']]);
        $unreadNotifications = $notifStmt->fetchColumn() ?? 0;
        
        // Get recent activities (last 5)
        $activityStmt = $db->query("
            SELECT a.*, u.full_name 
            FROM user_activity_logs a 
            LEFT JOIN users u ON a.user_id = u.user_id 
            ORDER BY a.performed_at DESC 
            LIMIT 5
        ");
        $recentActivities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check for urgent pending applications
        $urgentStmt = $db->query("
            SELECT COUNT(*) as urgent 
            FROM enrollment_applications 
            WHERE application_status = 'submitted' 
            AND DATEDIFF(CURDATE(), DATE(application_date)) > 3
        ");
        $urgentCount = $urgentStmt->fetchColumn() ?? 0;
        
        // Set system status based on urgent items
        $systemStatus = $urgentCount > 5 ? 'warning' : ($urgentCount > 0 ? 'info' : 'normal');
        
        // Update user's last activity time
        $updateStmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);
        
    }
} catch (Exception $e) {
    error_log("Admin Header Error: " . $e->getMessage());
    $systemStatus = 'error';
}

// Current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Skills Way Vocational Institute - Admin Panel">
    <meta name="author" content="Skills Way">
    
    <!-- Security Meta Tags -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https:;">
    
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>Admin Panel - Skills Way</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Datatables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary-color: #7209b7;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --danger-color: #e63946;
            --light-bg: #f8f9fa;
            --dark-bg: #212529;
            --sidebar-width: 280px;
            --header-height: 70px;
            --transition-speed: 0.3s;
        }
        
        [data-bs-theme="dark"] {
            --light-bg: #2d3748;
            --dark-bg: #1a202c;
        }
        
        /* Base Styles */
        body {
            font-family: 'Segoe UI', 'Roboto', system-ui, -apple-system, sans-serif;
            background-color: var(--light-bg);
            font-size: 0.9rem;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            min-height: 100vh;
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all var(--transition-speed);
            box-shadow: 3px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .user-info,
        .sidebar.collapsed .sidebar-heading {
            display: none !important;
        }
        
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .sidebar .logo {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }
        
        .sidebar .logo-text {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 8px;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            text-decoration: none;
            position: relative;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        
        .sidebar .nav-badge {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            padding: 2px 6px;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar .user-info {
            padding: 1rem;
            margin: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            overflow: hidden;
        }
        
        .sidebar .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed);
            min-height: 100vh;
        }
        
        .sidebar.collapsed ~ .main-content {
            margin-left: 70px;
        }
        
        /* Top Navigation Bar */
        .top-navbar {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            padding: 0 1.5rem;
        }
        
        [data-bs-theme="dark"] .top-navbar {
            background: var(--dark-bg);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .top-navbar .navbar-toggler {
            border: none;
            padding: 0.5rem;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        
        .top-navbar .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.7rem;
            padding: 2px 6px;
            min-width: 20px;
            height: 20px;
        }
        
        .top-navbar .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-width: 300px;
        }
        
        .top-navbar .dropdown-item {
            padding: 0.75rem 1rem;
        }
        
        .top-navbar .dropdown-item:hover {
            background-color: rgba(var(--primary-color), 0.1);
        }
        
        /* System Status Indicator */
        .system-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .system-status.normal { background-color: #4cc9f0; }
        .system-status.warning { background-color: #f72585; animation: pulse 2s infinite; }
        .system-status.error { background-color: #e63946; animation: pulse 1s infinite; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Notification Dropdown */
        .notification-item {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: background-color 0.2s;
        }
        
        .notification-item:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .notification-item.unread {
            background-color: rgba(var(--primary-color), 0.05);
        }
        
        .notification-item .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -280px;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.collapsed {
                margin-left: -70px;
            }
            
            .sidebar.collapsed.show {
                margin-left: 0;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(var(--primary-color), 0.3);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(var(--primary-color), 0.5);
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Utility Classes */
        .cursor-pointer {
            cursor: pointer;
        }
        
        .text-ellipsis {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .transition-all {
            transition: all var(--transition-speed);
        }
        
        /* Card Hover Effects */
        .card-hover {
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        /* Theme Toggle */
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .theme-toggle:hover {
            background: rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="logo d-flex align-items-center justify-content-between px-3 py-3">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-graduation-cap fa-2x text-white"></i>
                </div>
                <div>
                    <h4 class="logo-text mb-0 text-white">Skills Way</h4>
                    <small class="text-white-50">Admin Panel</small>
                </div>
            </div>
            <button class="btn btn-link text-white p-0" id="sidebarToggle">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- User Info -->
        <div class="user-info">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="<?php echo $user['full_name']; ?>">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0 text-white text-ellipsis"><?php echo $user['full_name']; ?></h6>
                    <small class="text-white-50 d-block text-ellipsis"><?php echo $user['email']; ?></small>
                    <small class="text-white-50">
                        <i class="fas fa-circle system-status <?php echo $systemStatus; ?>"></i>
                        <?php echo ucfirst($systemStatus); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'students.php' ? 'active' : ''; ?>" 
                   href="students.php">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                    <?php if ($stats['total_students'] > 0): ?>
                        <span class="nav-badge badge bg-info rounded-pill"><?php echo $stats['total_students']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'teachers.php' ? 'active' : ''; ?>" 
                   href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teachers</span>
                    <?php if ($stats['total_teachers'] > 0): ?>
                        <span class="nav-badge badge bg-success rounded-pill"><?php echo $stats['total_teachers']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'courses.php' ? 'active' : ''; ?>" 
                   href="courses.php">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                    <?php if ($stats['total_courses'] > 0): ?>
                        <span class="nav-badge badge bg-primary rounded-pill"><?php echo $stats['total_courses']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['enrollment-review.php', 'enrollment.php']) ? 'active' : ''; ?>" 
                   href="enrollment-review.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Applications</span>
                    <?php if ($stats['pending_applications'] > 0): ?>
                        <span class="nav-badge badge bg-warning rounded-pill"><?php echo $stats['pending_applications']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'batches.php' ? 'active' : ''; ?>" 
                   href="batches.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Batches</span>
                    <?php if ($stats['active_batches'] > 0): ?>
                        <span class="nav-badge badge bg-info rounded-pill"><?php echo $stats['active_batches']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'payments.php' ? 'active' : ''; ?>" 
                   href="payments.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                    <?php if ($stats['revenue_today'] > 0): ?>
                        <span class="nav-badge badge bg-success rounded-pill">Rs. <?php echo number_format($stats['revenue_today']); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>" 
                   href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <li class="nav-item mt-4">
                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-white-50">
                    <span>SYSTEM</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'activity-logs.php' ? 'active' : ''; ?>" 
                   href="activity-logs.php">
                    <i class="fas fa-history"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" 
                   href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'backup.php' ? 'active' : ''; ?>" 
                   href="backup.php">
                    <i class="fas fa-database"></i>
                    <span>Backup & Restore</span>
                </a>
            </li>
        </ul>
        
        <!-- Sidebar Footer -->
        <div class="position-absolute bottom-0 start-0 end-0 p-3">
            <div class="card bg-dark border-0">
                <div class="card-body p-3">
                    <small class="text-white-50 d-block mb-2">
                        <i class="fas fa-chart-line me-1"></i> Quick Stats
                    </small>
                    <div class="d-flex justify-content-between small text-white mb-1">
                        <span>Students</span>
                        <span class="fw-semibold"><?php echo number_format($stats['total_students']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-white mb-1">
                        <span>Teachers</span>
                        <span class="fw-semibold"><?php echo number_format($stats['total_teachers']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-white mb-1">
                        <span>Courses</span>
                        <span class="fw-semibold"><?php echo number_format($stats['total_courses']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-white">
                        <span>Revenue (Month)</span>
                        <span class="fw-semibold text-success">Rs. <?php echo number_format($stats['revenue_month']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="../logout.php" class="btn btn-outline-light btn-sm w-100" 
                   onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation Bar -->
        <nav class="navbar navbar-expand-lg top-navbar">
            <div class="container-fluid">
                <!-- Mobile Sidebar Toggle -->
                <button class="navbar-toggler" type="button" id="mobileSidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="d-none d-md-flex me-auto">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i></a></li>
                        <?php if (isset($pageTitle)): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($pageTitle); ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>
                
                <!-- Right Side Controls -->
                <div class="d-flex align-items-center">
                    <!-- Theme Toggle -->
                    <div class="theme-toggle me-3" id="themeToggle" title="Toggle Theme">
                        <i class="fas fa-moon"></i>
                    </div>
                    
                    <!-- Notifications Dropdown -->
                    <div class="dropdown me-3">
                        <button class="btn btn-link text-dark p-0 position-relative" type="button" 
                                id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell fa-lg"></i>
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="notification-badge badge bg-danger rounded-pill"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications (<?php echo $unreadNotifications; ?> unread)</h6></li>
                            <?php if ($unreadNotifications > 0): ?>
                                <li><a class="dropdown-item text-primary small" href="#" onclick="markAllNotificationsAsRead()">
                                    <i class="fas fa-check-double me-2"></i> Mark all as read
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <div class="px-3 py-2 text-center">
                                    <small class="text-muted">No new notifications</small>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="notifications.php">
                                <i class="fas fa-eye me-1"></i> View all notifications
                            </a></li>
                        </ul>
                    </div>
                    
                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-link text-dark p-0 d-flex align-items-center" type="button" 
                                id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="me-2 text-end d-none d-sm-block">
                                <div class="small fw-semibold"><?php echo $user['full_name']; ?></div>
                                <div class="small text-muted">Administrator</div>
                            </div>
                            <div class="user-avatar">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                         alt="<?php echo $user['full_name']; ?>" class="rounded-circle" width="40" height="40">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-2x text-primary"></i>
                                <?php endif; ?>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                            <li><h6 class="dropdown-header">Signed in as</h6></li>
                            <li><div class="dropdown-item disabled">
                                <strong><?php echo $user['full_name']; ?></strong><br>
                                <small class="text-muted"><?php echo $user['email']; ?></small>
                            </div></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i> My Profile
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php" 
                                   onclick="return confirm('Are you sure you want to logout?');">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Main Content Area -->
        <div class="container-fluid py-4">
            <!-- Page Title -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0 text-primary">
                                <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?>
                            </h1>
                            <p class="text-muted mb-0">
                                <?php echo isset($pageDescription) ? htmlspecialchars($pageDescription) : 'Admin Panel Overview'; ?>
                            </p>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-<?php echo $systemStatus; ?> me-3">
                                <i class="fas fa-circle system-status <?php echo $systemStatus; ?>"></i>
                                System: <?php echo ucfirst($systemStatus); ?>
                            </span>
                            <button class="btn btn-outline-primary btn-sm" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Area (will be filled by individual pages) -->
            <div class="row">
                <div class="col-12">
                    <!-- Content goes here -->