<?php
// Note: Authentication is now handled by middleware before including this header
// Get current user from session
$user = [
    'full_name' => $_SESSION['full_name'] ?? 'Teacher',
    'user_type' => $_SESSION['user_type'] ?? 'teacher'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Teacher Portal'; ?> - Skills Way LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { background: linear-gradient(135deg, #28a745, #20c997); min-height: 100vh; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 10px 20px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="fas fa-chalkboard-teacher fa-3x text-white mb-2"></i>
                        <h5 class="text-white">Teacher Portal</h5>
                    </div>
                    <div class="px-3 mb-3">
                        <small class="text-white-50">Welcome</small>
                        <div class="text-white"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'classes.php' ? 'active' : ''; ?>" href="classes.php">
                                <i class="fas fa-chalkboard me-2"></i>Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>" href="students.php">
                                <i class="fas fa-users me-2"></i>Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
                                <i class="fas fa-bullhorn me-2"></i>Announcements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php">
                                <i class="fas fa-calendar me-2"></i>Calendar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                                <i class="fas fa-envelope me-2"></i>Messages
                            </a>
                        </li>
                    </ul>
                    <div class="mt-auto p-3">
                        <a href="../logout.php" class="btn btn-outline-light btn-sm w-100">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">