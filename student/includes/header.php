<?php
require_once '../config/session.php';
require_once '../config/auth.php';
require_once '../config/database.php';

$session = new SessionManager();
$auth = new Authentication();
$db = (new Database())->getConnection();

// Check authentication
if (!$session->isLoggedIn() || !in_array($session->get('user_type'), ['student', 'teacher', 'admin'])) {
    header("Location: ../login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $session->get('user_id');
$user_type = $session->get('user_type');
$user = $auth->getCurrentUser();

// Get user notifications count
$notification_count = 0;
$unread_messages = 0;

if ($user_id) {
    // Get unread notifications count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$user_id]);
    $notification_count = $stmt->fetchColumn();

    // Get unread messages count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE recipient_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$user_id]);
    $unread_messages = $stmt->fetchColumn();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$page_groups = [
    'dashboard' => ['dashboard.php'],
    'academic' => ['available-courses.php', 'classes.php', 'materials.php', 'assignments.php', 'quizzes.php'],
    'communication' => ['messages.php', 'classmates.php', 'announcements.php'],
    'progress' => ['progress.php', 'grades.php', 'certificates.php'],
    'resources' => ['library.php', 'calendar.php', 'timetable.php'],
    'settings' => ['profile.php', 'settings.php', 'account.php']
];

// Determine active group
$active_group = '';
foreach ($page_groups as $group => $pages) {
    if (in_array($current_page, $pages)) {
        $active_group = $group;
        break;
    }
}

// Get upcoming deadlines
$upcoming_deadlines = [];
if ($user_type === 'student') {
    $stmt = $db->prepare("
        SELECT a.assignment_id, a.title as assignment_title, a.due_date, 
               DATEDIFF(a.due_date, CURDATE()) as days_left,
               c.course_name
        FROM assignments a
        JOIN batches b ON a.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        JOIN enrollment_applications ea ON b.batch_id = ea.batch_id
        WHERE ea.user_id = ? 
        AND ea.application_status = 'approved'
        AND a.due_date >= CURDATE()
        AND a.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM assignment_submissions s
            WHERE s.assignment_id = a.assignment_id 
            AND s.user_id = ea.user_id
        )
        ORDER BY a.due_date ASC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $upcoming_deadlines = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="Skills Way Learning Management System - Student Portal">
    <meta name="keywords" content="LMS, Education, Vocational, Training, Skills">
    <meta name="author" content="Skills Way Institute">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle ?? 'Student Dashboard'); ?> - Skills Way LMS">
    <meta property="og:description" content="Access your courses, assignments, and learning materials">
    <meta property="og:type" content="website">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Student Dashboard'); ?> - Skills Way LMS</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/apple-touch-icon.png">

    <!-- Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/student.css">

    <!-- Page-specific CSS -->
    <?php if (isset($pageTitle)): ?>
        <link rel="stylesheet" href="../assets/css/<?php echo strtolower(str_replace(' ', '-', $pageTitle)); ?>.css">
    <?php endif; ?>

    <!-- Accessibility enhancements -->
    <style>
        :focus {
            outline: 3px solid #0d6efd;
            outline-offset: 2px;
        }

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
    </style>

    <!-- PWA manifest -->
    <link rel="manifest" href="../manifest.json">

    <!-- Theme color for mobile browsers -->
    <meta name="theme-color" content="#0d6efd">

    <!-- Preload critical resources -->
    <link rel="preload" href="../assets/js/main.js" as="script">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" as="script">

    <!-- Scripts for header functionality -->
    <script>
        // Theme toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const htmlElement = document.documentElement;

            // Check for saved theme or prefer-color-scheme
            const savedTheme = localStorage.getItem('theme') ||
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            htmlElement.setAttribute('data-bs-theme', savedTheme);
            updateThemeIcon(savedTheme);

            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const currentTheme = htmlElement.getAttribute('data-bs-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                    htmlElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    updateThemeIcon(newTheme);
                });
            }

            function updateThemeIcon(theme) {
                if (themeIcon) {
                    themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                }
            }

            // Load notifications
            loadNotifications();
            loadMessages();

            // Global search
            const globalSearch = document.getElementById('globalSearch');
            const mobileSearch = document.getElementById('mobileGlobalSearch');

            if (globalSearch) {
                globalSearch.addEventListener('input', debounce(searchFunction, 300));
            }
            if (mobileSearch) {
                mobileSearch.addEventListener('input', debounce(searchFunction, 300));
            }

            // Mark all notifications as read
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', markAllNotificationsAsRead);
            }

            // Auto-update notifications every 60 seconds
            setInterval(loadNotifications, 60000);
            setInterval(loadMessages, 60000);

            // Navbar scroll effect
            window.addEventListener('scroll', function() {
                const navbar = document.getElementById('mainNavbar');
                if (navbar) {
                    if (window.scrollY > 50) {
                        navbar.classList.add('navbar-shadow');
                    } else {
                        navbar.classList.remove('navbar-shadow');
                    }
                }
            });

            // Close search results when clicking outside
            document.addEventListener('click', function(event) {
                const searchContainer = document.querySelector('.search-container');
                if (searchContainer && !searchContainer.contains(event.target)) {
                    hideSearchResults();
                }
            });
        });

        // Load notifications via AJAX
        function loadNotifications() {
            const notificationsList = document.getElementById('notificationsList');
            if (!notificationsList) return;

            fetch('ajax/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificationsList.innerHTML = data.html;

                        // Update notification count
                        const badge = document.querySelector('#notificationsDropdown .badge');
                        if (badge) {
                            if (data.count > 0) {
                                badge.textContent = Math.min(data.count, 9);
                                badge.style.display = 'block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }

        // Load messages via AJAX
        function loadMessages() {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            fetch('ajax/get_messages_preview.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messagesList.innerHTML = data.html;

                        // Update message count
                        const badge = document.querySelector('#messagesDropdown .badge');
                        if (badge) {
                            if (data.count > 0) {
                                badge.textContent = Math.min(data.count, 9);
                                badge.style.display = 'block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }

        // Mark all notifications as read
        function markAllNotificationsAsRead() {
            fetch('ajax/mark_all_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications(); // Refresh the list
                        showToast('All notifications marked as read', 'success');
                    }
                })
                .catch(error => console.error('Error marking notifications as read:', error));
        }

        // Global search function
        function searchFunction() {
            const searchTerm = this.value.trim();
            if (searchTerm.length < 2) {
                hideSearchResults();
                return;
            }

            fetch(`ajax/search.php?q=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    displaySearchResults(data);
                })
                .catch(error => console.error('Search error:', error));
        }

        function displaySearchResults(data) {
            const resultsContainer = document.getElementById('searchResults');
            if (!resultsContainer || !data.success) return;

            let html = '<div class="p-2">';

            if (data.results.length === 0) {
                html += '<div class="text-center text-muted py-3">No results found</div>';
            } else {
                data.results.forEach(result => {
                    html += `
                    <a href="${result.url}" class="dropdown-item d-flex align-items-center py-2">
                        <i class="${result.icon} me-3 text-muted"></i>
                        <div>
                            <div class="fw-semibold">${result.title}</div>
                            <small class="text-muted">${result.subtitle}</small>
                        </div>
                    </a>
                `;
                });
            }

            html += '</div>';
            resultsContainer.innerHTML = html;
            resultsContainer.style.display = 'block';
        }

        function hideSearchResults() {
            const resultsContainer = document.getElementById('searchResults');
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
            }
        }

        // Debounce utility
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            // Create toast element if not exists
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
                toastContainer.style.zIndex = '1050';
                document.body.appendChild(toastContainer);
            }

            const toastId = 'toast-' + Date.now();
            const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                delay: 3000
            });
            toast.show();

            // Remove toast after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }
    </script>
</head>

<body class="d-flex flex-column min-vh-100">
    <!-- Accessibility Skip to Content -->
    <a href="#main-content" class="btn btn-primary skip-to-content">
        <span class="sr-only">Skip to main content</span>
        Skip to Content
    </a>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-xl navbar-dark bg-primary shadow-sm fixed-top" id="mainNavbar">
        <div class="container-fluid">
            <!-- Brand & Mobile Toggle -->
            <div class="d-flex align-items-center">
                <button class="navbar-toggler me-2" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                    <span class="navbar-toggler-icon"></span>
                    <span class="sr-only">Toggle sidebar</span>
                </button>

                <a class="navbar-brand d-flex align-items-center" href="dashboard.php" aria-label="Skills Way LMS Home">
                    <div class="brand-logo me-2">
                        <i class="fas fa-graduation-cap fa-lg"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold">Skills Way</span>
                        <small class="brand-subtitle">Vocational Institute</small>
                    </div>
                </a>
            </div>

            <!-- Search Bar (Desktop) -->
            <div class="d-none d-xl-flex mx-4 flex-grow-1" style="max-width: 500px;">
                <div class="input-group search-container">
                    <input type="search" class="form-control" id="globalSearch"
                        placeholder="Search courses, materials, people..."
                        aria-label="Search">
                    <button class="btn btn-light" type="button" id="searchButton">
                        <i class="fas fa-search"></i>
                        <span class="sr-only">Search</span>
                    </button>
                    <div class="search-results dropdown-menu w-100" id="searchResults"
                        aria-labelledby="searchButton">
                        <!-- Search results will appear here -->
                    </div>
                </div>
            </div>

            <!-- Right Side Navigation -->
            <div class="d-flex align-items-center">
                <!-- Theme Toggle -->
                <button class="btn btn-link text-light me-2" id="themeToggle"
                    aria-label="Toggle dark/light mode">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                <!-- Search Toggle (Mobile) -->
                <button class="btn btn-link text-light d-xl-none me-2"
                    data-bs-toggle="collapse" data-bs-target="#mobileSearch"
                    aria-expanded="false" aria-label="Toggle search">
                    <i class="fas fa-search"></i>
                </button>

                <!-- Notifications Dropdown -->
                <div class="dropdown me-2">
                    <button class="btn btn-link text-light position-relative"
                        type="button" id="notificationsDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo min($notification_count, 9); ?>
                                <span class="visually-hidden">unread notifications</span>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0"
                        aria-labelledby="notificationsDropdown" style="min-width: 320px;">
                        <div class="card border-0 shadow">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Notifications</h6>
                                <?php if ($notification_count > 0): ?>
                                    <button class="btn btn-sm btn-outline-light" id="markAllRead">
                                        Mark all read
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                                <div id="notificationsList">
                                    <!-- Notifications will be loaded via AJAX -->
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="notifications.php" class="btn btn-sm btn-outline-primary w-100">
                                    View All Notifications
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages Dropdown -->
                <div class="dropdown me-2">
                    <button class="btn btn-link text-light position-relative"
                        type="button" id="messagesDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        aria-label="Messages">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_messages > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo min($unread_messages, 9); ?>
                                <span class="visually-hidden">unread messages</span>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0"
                        aria-labelledby="messagesDropdown" style="min-width: 320px;">
                        <div class="card border-0 shadow">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">Messages</h6>
                            </div>
                            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                                <div id="messagesList">
                                    <!-- Messages will be loaded via AJAX -->
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="messages.php" class="btn btn-sm btn-outline-primary w-100">
                                    View All Messages
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Dropdown -->
                <div class="dropdown me-2">
                    <button class="btn btn-link text-light"
                        type="button" id="quickActionsDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        aria-label="Quick Actions">
                        <i class="fas fa-bolt"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Quick Actions</h6>
                        <a class="dropdown-item" href="assignments.php?action=submit">
                            <i class="fas fa-plus-circle text-success me-2"></i>
                            Submit Assignment
                        </a>
                        <a class="dropdown-item" href="courses.php?enroll=1">
                            <i class="fas fa-book text-info me-2"></i>
                            Enroll in Course
                        </a>
                        <a class="dropdown-item" href="calendar.php">
                            <i class="fas fa-calendar text-warning me-2"></i>
                            View Calendar
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="help.php">
                            <i class="fas fa-question-circle text-secondary me-2"></i>
                            Help Center
                        </a>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-link text-light d-flex align-items-center"
                        type="button" id="userProfileDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        aria-label="User menu">
                        <div class="position-relative me-2">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>"
                                    class="rounded-circle" width="32" height="32"
                                    alt="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    onerror="this.src='../assets/images/default-avatar.png'">
                            <?php else: ?>
                                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center"
                                    style="width: 32px; height: 32px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <?php if ($user_type === 'admin'): ?>
                                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-warning"
                                    style="font-size: 0.6rem; padding: 0.2rem 0.3rem;">
                                    Admin
                                </span>
                            <?php elseif ($user_type === 'teacher'): ?>
                                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-info"
                                    style="font-size: 0.6rem; padding: 0.2rem 0.3rem;">
                                    Teacher
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-none d-lg-flex flex-column text-start">
                            <small class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></small>
                            <small class="text-light opacity-75"><?php echo ucfirst($user_type); ?></small>
                        </div>
                        <i class="fas fa-chevron-down ms-1"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow"
                        aria-labelledby="userProfileDropdown">
                        <div class="dropdown-header">
                            <h6 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user-circle me-2"></i>My Profile
                        </a>
                        <a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <a class="dropdown-item" href="change-password.php">
                            <i class="fas fa-key me-2"></i>Change Password
                        </a>
                        <div class="dropdown-divider"></div>
                        <?php if ($user_type === 'admin'): ?>
                            <a class="dropdown-item" href="../admin/dashboard.php">
                                <i class="fas fa-shield-alt me-2"></i>Admin Panel
                            </a>
                        <?php elseif ($user_type === 'teacher'): ?>
                            <a class="dropdown-item" href="../teacher/dashboard.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Panel
                            </a>
                        <?php endif; ?>
                        <a class="dropdown-item" href="help.php">
                            <i class="fas fa-question-circle me-2"></i>Help & Support
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Search Collapse -->
    <div class="collapse bg-light shadow-sm" id="mobileSearch">
        <div class="container-fluid py-2">
            <div class="input-group">
                <input type="search" class="form-control" id="mobileGlobalSearch"
                    placeholder="Search..." aria-label="Search on mobile">
                <button class="btn btn-primary" type="button" id="mobileSearchButton">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Sidebar Offcanvas (Mobile) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
        <div class="offcanvas-header border-bottom">
            <div class="d-flex align-items-center">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>"
                        class="rounded-circle me-3" width="40" height="40"
                        alt="<?php echo htmlspecialchars($user['full_name']); ?>"
                        onerror="this.src='../assets/images/default-avatar.png'">
                <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3"
                        style="width: 40px; height: 40px;">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h6 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                    <small class="text-muted"><?php echo ucfirst($user_type); ?></small>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="Close sidebar"></button>
        </div>
        <div class="offcanvas-body p-0">
            <!-- Navigation Menu -->
            <nav class="nav flex-column">
                <div class="accordion" id="sidebarAccordion">
                    <?php foreach ($page_groups as $group => $pages):
                        $group_title = ucfirst($group);
                        $group_icons = [
                            'dashboard' => 'fas fa-tachometer-alt',
                            'academic' => 'fas fa-graduation-cap',
                            'communication' => 'fas fa-comments',
                            'progress' => 'fas fa-chart-line',
                            'resources' => 'fas fa-book',
                            'settings' => 'fas fa-cog'
                        ];
                        $group_icon = $group_icons[$group] ?? 'fas fa-folder';
                    ?>
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?php echo $group !== $active_group ? 'collapsed' : ''; ?>"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse<?php echo ucfirst($group); ?>"
                                    aria-expanded="<?php echo $group === $active_group ? 'true' : 'false'; ?>"
                                    aria-controls="collapse<?php echo ucfirst($group); ?>">
                                    <i class="<?php echo $group_icon; ?> me-2"></i>
                                    <?php echo $group_title; ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo ucfirst($group); ?>"
                                class="accordion-collapse collapse <?php echo $group === $active_group ? 'show' : ''; ?>"
                                data-bs-parent="#sidebarAccordion">
                                <div class="accordion-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($pages as $page):
                                            $page_name = ucfirst(str_replace(['.php', '_'], ['', ' '], $page));
                                            $page_icons = [
                                                'dashboard.php' => 'fas fa-tachometer-alt',
                                                'available-courses.php' => 'fas fa-book-open',
                                                'classes.php' => 'fas fa-users',
                                                'materials.php' => 'fas fa-file-alt',
                                                'assignments.php' => 'fas fa-tasks',
                                                'quizzes.php' => 'fas fa-question-circle',
                                                'messages.php' => 'fas fa-envelope',
                                                'classmates.php' => 'fas fa-user-friends',
                                                'announcements.php' => 'fas fa-bullhorn',
                                                'progress.php' => 'fas fa-chart-bar',
                                                'grades.php' => 'fas fa-star',
                                                'certificates.php' => 'fas fa-certificate',
                                                'library.php' => 'fas fa-book-reader',
                                                'calendar.php' => 'fas fa-calendar-alt',
                                                'timetable.php' => 'fas fa-clock',
                                                'profile.php' => 'fas fa-user-edit',
                                                'settings.php' => 'fas fa-sliders-h',
                                                'account.php' => 'fas fa-user-cog'
                                            ];
                                            $page_icon = $page_icons[$page] ?? 'fas fa-file';
                                        ?>
                                            <a href="<?php echo $page; ?>"
                                                class="list-group-item list-group-item-action border-0 rounded-0 py-3 ps-5 
                                              <?php echo $current_page === $page ? 'active text-white' : 'text-dark'; ?>">
                                                <i class="<?php echo $page_icon; ?> me-2"></i>
                                                <?php echo $page_name; ?>
                                                <?php if ($page === 'messages.php' && $unread_messages > 0): ?>
                                                    <span class="badge bg-danger float-end mt-1">
                                                        <?php echo min($unread_messages, 9); ?>
                                                    </span>
                                                <?php elseif ($page === 'notifications.php' && $notification_count > 0): ?>
                                                    <span class="badge bg-danger float-end mt-1">
                                                        <?php echo min($notification_count, 9); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </nav>

            <!-- Upcoming Deadlines (Students only) -->
            <?php if ($user_type === 'student' && !empty($upcoming_deadlines)): ?>
                <div class="border-top mt-3 pt-3 px-3">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-clock me-1"></i>Upcoming Deadlines
                    </h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_deadlines as $deadline):
                            $days_left = $deadline['days_left'];
                            $badge_class = $days_left <= 1 ? 'bg-danger' : ($days_left <= 3 ? 'bg-warning' : 'bg-info');
                            $badge_text = $days_left == 0 ? 'Today' : ($days_left == 1 ? 'Tomorrow' : "$days_left days");
                        ?>
                            <a href="assignments.php?assignment_id=<?php echo $deadline['assignment_id']; ?>"
                                class="list-group-item list-group-item-action border-0 py-2 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <small class="d-block text-truncate" style="max-width: 180px;">
                                            <?php echo htmlspecialchars($deadline['assignment_title']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($deadline['course_name']); ?>
                                        </small>
                                    </div>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $badge_text; ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a href="assignments.php" class="btn btn-sm btn-outline-primary w-100 mt-2">
                        View All Assignments
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="offcanvas-footer border-top p-3">
            <div class="d-grid gap-2">
                <a href="../logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main id="main-content" class="flex-grow-1 pt-5">
        <div class="container-fluid py-4">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <?php
                    $uri = $_SERVER['REQUEST_URI'];
                    $parts = explode('/', trim($uri, '/'));
                    $page_name = end($parts);
                    $page_name = str_replace(['.php', '_'], ['', ' '], $page_name);
                    ?>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?php echo ucwords($page_name); ?>
                    </li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <?php if (isset($pageIcon)): ?>
                                <i class="<?php echo $pageIcon; ?> me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?>
                        </h1>
                        <?php if (isset($pageSubtitle)): ?>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($pageSubtitle); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <!-- Page-specific actions -->
                        <?php if (isset($pageActions)): ?>
                            <?php echo $pageActions; ?>
                        <?php endif; ?>

                        <!-- Print button -->
                        <button class="btn btn-outline-secondary" onclick="window.print()"
                            aria-label="Print this page">
                            <i class="fas fa-print"></i>
                        </button>

                        <!-- Help button -->
                        <button class="btn btn-outline-info" data-bs-toggle="modal"
                            data-bs-target="#helpModal" aria-label="Get help">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>
            </div>