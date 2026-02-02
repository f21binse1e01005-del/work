<?php
/**
 * Admin Dashboard Main Page
 * File: admin/dashboard.php
 */

session_start();
require_once '../config/database.php';

// Check admin access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();

// Initialize all variables with default values
$totalStudents = 0;
$totalTeachers = 0;
$pendingApplications = 0;
$revenueToday = 0;
$totalCourses = 0;
$activeBatches = 0;
$recentEnrollments = 0;
$monthlyRevenue = 0;
$courseStats = [];
$recentActivities = [];
$upcomingBatches = [];
$paymentStats = [];
$registrationTrend = [];
$errorMessage = null;

// Always available system info
$serverInfo = [
    'php_version' => PHP_VERSION,
    'database' => 'MariaDB',
    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
    'server_time' => date('Y-m-d H:i:s')
];

try {
    // Get total students (using your users table)
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'student' AND account_status = 'active'");
    $totalStudents = $stmt->fetchColumn();
    
    // Get total teachers (using your users table)
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'teacher' AND account_status = 'active'");
    $totalTeachers = $stmt->fetchColumn();
    
    // Get pending applications (using your enrollment_applications table)
    $stmt = $db->query("SELECT COUNT(*) as total FROM enrollment_applications WHERE application_status IN ('submitted', 'under_review')");
    $pendingApplications = $stmt->fetchColumn();
    
    // Get today's revenue
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT SUM(payment_amount) as total FROM enrollment_applications WHERE payment_status = 'paid' AND DATE(application_date) = ?");
    $stmt->execute([$today]);
    $revenueToday = $stmt->fetchColumn() ?: 0;
    
    // Get total courses (using your courses table)
    $stmt = $db->query("SELECT COUNT(*) as total FROM courses WHERE is_active = 1");
    $totalCourses = $stmt->fetchColumn();
    
    // Get active batches (using your batches table)
    $stmt = $db->query("SELECT COUNT(*) as total FROM batches WHERE status = 'ongoing'");
    $activeBatches = $stmt->fetchColumn();
    
    // Get recent enrollments (last 7 days)
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM enrollment_applications WHERE DATE(application_date) >= ?");
    $stmt->execute([$weekAgo]);
    $recentEnrollments = $stmt->fetchColumn() ?: 0;
    
    // Get monthly revenue
    $currentMonth = date('Y-m');
    $stmt = $db->prepare("SELECT SUM(payment_amount) as total FROM enrollment_applications WHERE payment_status = 'paid' AND DATE_FORMAT(application_date, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $monthlyRevenue = $stmt->fetchColumn() ?: 0;
    
    // Get course enrollment statistics
    $courseStats = $db->query("
        SELECT c.course_name, COUNT(ea.application_id) as enrollments
        FROM courses c
        LEFT JOIN enrollment_applications ea ON c.course_id = ea.course_id
        WHERE c.is_active = 1
        GROUP BY c.course_id, c.course_name
        ORDER BY enrollments DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activities from user_activity_logs
    $recentActivities = $db->query("
        SELECT u.full_name, ual.activity_type, ual.activity_details, ual.performed_at
        FROM user_activity_logs ual
        LEFT JOIN users u ON ual.user_id = u.user_id
        ORDER BY ual.performed_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming batches
    $upcomingBatches = $db->query("
        SELECT b.batch_name, c.course_name, b.start_date, b.status,
               (SELECT COUNT(*) FROM enrollment_applications ea WHERE ea.batch_id = b.batch_id) as enrolled
        FROM batches b
        JOIN courses c ON b.course_id = c.course_id
        WHERE b.status = 'upcoming' AND b.start_date >= CURDATE()
        ORDER BY b.start_date ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment statistics
    $paymentStats = $db->query("
        SELECT 
            payment_status,
            COUNT(*) as count,
            SUM(payment_amount) as total_amount
        FROM enrollment_applications
        WHERE payment_status IN ('paid', 'pending', 'partial')
        GROUP BY payment_status
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user registration trend (last 30 days)
    $registrationTrend = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE DATE(created_at) = ? AND user_type = 'student'
        ");
        $stmt->execute([$date]);
        $registrationTrend[$date] = $stmt->fetchColumn();
    }
    
} catch (Exception $e) {
    $errorMessage = "Database Error: " . $e->getMessage();
}

// Include header
try {
    require_once 'includes/header.php';
} catch (Exception $e) {
    // Fallback basic header
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            .stat-card { transition: transform 0.2s; }
            .stat-card:hover { transform: translateY(-5px); }
            .card-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        </style>
    </head>
    <body>';
}
?>

<div class="container-fluid">
    <!-- Top Bar -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div class="d-flex align-items-center">
            <i class="fas fa-tachometer-alt fa-2x text-primary me-3"></i>
            <div>
                <h1 class="h2 mb-0">Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>!</p>
            </div>
        </div>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="text-end">
                <div class="text-muted small">
                    <i class="fas fa-clock me-1"></i>
                    <span id="current-time"><?php echo date('h:i:s A'); ?></span>
                </div>
                <div class="text-muted small">
                    <i class="fas fa-calendar me-1"></i>
                    <?php echo date('F j, Y'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($errorMessage)): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Note:</strong> <?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <!-- Total Students -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-primary border-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Students</div>
                            <div class="h2 mb-0 fw-bold text-gray-800"><?php echo number_format($totalStudents); ?></div>
                            <div class="mt-2 mb-0">
                                <span class="text-success small">
                                    <i class="fas fa-arrow-up me-1"></i>
                                    <?php echo $recentEnrollments; ?> new this week
                                </span>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="card-icon bg-primary bg-opacity-10 text-primary mx-auto">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="students.php" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fas fa-eye me-1"></i>View Students
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Teachers -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-success border-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Teachers</div>
                            <div class="h2 mb-0 fw-bold text-gray-800"><?php echo number_format($totalTeachers); ?></div>
                            <div class="mt-2 mb-0">
                                <span class="text-muted small">
                                    <i class="fas fa-chalkboard me-1"></i>
                                    <?php echo $totalCourses; ?> courses
                                </span>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="card-icon bg-success bg-opacity-10 text-success mx-auto">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="teachers.php" class="btn btn-sm btn-outline-success w-100">
                            <i class="fas fa-user-plus me-1"></i>Manage Teachers
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Applications -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-warning border-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Applications</div>
                            <div class="h2 mb-0 fw-bold text-gray-800"><?php echo number_format($pendingApplications); ?></div>
                            <div class="mt-2 mb-0">
                                <span class="text-danger small">
                                    <i class="fas fa-clock me-1"></i>
                                    Requires attention
                                </span>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="card-icon bg-warning bg-opacity-10 text-warning mx-auto">
                                <i class="fas fa-file-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <?php if ($pendingApplications > 0): ?>
                            <a href="enrollment-review.php" class="btn btn-sm btn-warning w-100">
                                <i class="fas fa-check-circle me-1"></i>Review Now
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-warning w-100" disabled>
                                <i class="fas fa-check me-1"></i>All Caught Up
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Revenue -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-info border-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Monthly Revenue</div>
                            <div class="h2 mb-0 fw-bold text-gray-800">Rs. <?php echo number_format($monthlyRevenue, 2); ?></div>
                            <div class="mt-2 mb-0">
                                <span class="text-info small">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    Today: Rs. <?php echo number_format($revenueToday, 2); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="card-icon bg-info bg-opacity-10 text-info mx-auto">
                                <i class="fas fa-credit-card"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="payments.php" class="btn btn-sm btn-outline-info w-100">
                            <i class="fas fa-chart-line me-1"></i>View Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions & Recent Activities -->
    <div class="row mb-4">
        <!-- Quick Actions -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="enrollment-review.php" class="btn btn-outline-primary w-100 h-100 py-3">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <div>Review Applications</div>
                                <?php if ($pendingApplications > 0): ?>
                                    <span class="badge bg-danger rounded-pill mt-2"><?php echo $pendingApplications; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="courses.php" class="btn btn-outline-success w-100 h-100 py-3">
                                <i class="fas fa-plus fa-2x mb-2"></i>
                                <div>Add Course</div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="batches.php" class="btn btn-outline-info w-100 h-100 py-3">
                                <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                                <div>Create Batch</div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="teachers.php" class="btn btn-outline-warning w-100 h-100 py-3">
                                <i class="fas fa-user-plus fa-2x mb-2"></i>
                                <div>Add Teacher</div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="reports.php" class="btn btn-outline-secondary w-100 h-100 py-3">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <div>Reports</div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="settings.php" class="btn btn-outline-dark w-100 h-100 py-3">
                                <i class="fas fa-cog fa-2x mb-2"></i>
                                <div>Settings</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-history me-2"></i>Recent Activities
                    </h6>
                    <a href="activity-logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <tbody>
                                <?php if (empty($recentActivities)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                                            <div>No recent activities</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <tr class="activity-item">
                                        <td width="60">
                                            <div class="icon-small bg-light text-primary">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($activity['full_name'] ?: 'System'); ?></div>
                                            <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></small>
                                        </td>
                                        <td class="text-end">
                                            <small class="text-muted">
                                                <?php echo timeAgo($activity['performed_at']); ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Courses & Batches -->
    <div class="row mb-4">
        <!-- Top Courses -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-book me-2"></i>Top Courses
                    </h6>
                    <a href="courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th class="text-end">Enrollments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($courseStats)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-3">No courses found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courseStats as $course): 
                                        $percentage = $totalStudents > 0 ? round(($course['enrollments'] / $totalStudents) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                            <div class="progress" style="height: 4px; width: 100px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-end align-middle">
                                            <span class="badge bg-primary rounded-pill px-3 py-2">
                                                <?php echo $course['enrollments']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Batches -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Upcoming Batches
                    </h6>
                    <a href="batches.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Course</th>
                                    <th class="text-end">Enrolled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($upcomingBatches)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">No upcoming batches</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($upcomingBatches as $batch): 
                                        $daysLeft = floor((strtotime($batch['start_date']) - time()) / (60 * 60 * 24));
                                        $statusClass = $daysLeft <= 7 ? 'warning' : 'info';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($batch['batch_name']); ?></div>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($batch['start_date'])); ?>
                                                <span class="badge bg-<?php echo $statusClass; ?> ms-2">
                                                    <?php echo $daysLeft > 0 ? "in $daysLeft days" : 'Today'; ?>
                                                </span>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($batch['course_name']); ?></td>
                                        <td class="text-end align-middle">
                                            <span class="badge bg-light text-dark px-3 py-2">
                                                <?php echo $batch['enrolled']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Info -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-server me-2"></i>System Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body text-center py-4">
                                    <div class="text-primary mb-2">
                                        <i class="fas fa-code fa-2x"></i>
                                    </div>
                                    <div class="text-muted small mb-2">PHP Version</div>
                                    <div class="h5 mb-0 fw-bold"><?php echo htmlspecialchars($serverInfo['php_version'] ?? PHP_VERSION); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body text-center py-4">
                                    <div class="text-success mb-2">
                                        <i class="fas fa-database fa-2x"></i>
                                    </div>
                                    <div class="text-muted small mb-2">Database</div>
                                    <div class="h5 mb-0 fw-bold"><?php echo htmlspecialchars($serverInfo['database'] ?? 'MariaDB'); ?></div>
                                    <small class="text-success">
                                        <i class="fas fa-check-circle me-1"></i>Connected
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body text-center py-4">
                                    <div class="text-info mb-2">
                                        <i class="fas fa-memory fa-2x"></i>
                                    </div>
                                    <div class="text-muted small mb-2">Memory Usage</div>
                                    <div class="h5 mb-0 fw-bold"><?php echo htmlspecialchars($serverInfo['memory_usage'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body text-center py-4">
                                    <div class="text-warning mb-2">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                    <div class="text-muted small mb-2">Server Time</div>
                                    <div class="h5 mb-0 fw-bold"><?php echo date('h:i A'); ?></div>
                                    <small class="text-muted"><?php echo date('M d, Y'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Update current time
function updateTime() {
    const now = new Date();
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit',
            hour12: true 
        });
        timeElement.textContent = timeString;
    }
}
updateTime();
setInterval(updateTime, 1000);

// Refresh dashboard
function refreshDashboard() {
    location.reload();
}

// Auto-refresh notification (every 5 minutes)
setTimeout(() => {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-info alert-dismissible fade show position-fixed bottom-0 end-0 m-3';
    alertDiv.style.zIndex = '1050';
    alertDiv.style.maxWidth = '350px';
    alertDiv.innerHTML = `
        <i class="fas fa-sync-alt me-2"></i>
        <strong>Auto-refresh</strong> in <span id="refresh-countdown">60</span> seconds
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    let countdown = 60;
    const countdownElement = document.getElementById('refresh-countdown');
    const countdownInterval = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            refreshDashboard();
        }
    }, 1000);
    
    // Auto-dismiss after 60 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 60000);
}, 300000); // 5 minutes
</script>

<style>
.stat-card {
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.icon-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.activity-item {
    border-left: 3px solid transparent;
    transition: all 0.3s ease;
}
.activity-item:hover {
    background-color: #f8f9fa;
    border-left-color: #4e73df;
}
.progress {
    border-radius: 10px;
}
.progress-bar {
    border-radius: 10px;
}
</style>

<?php
// Helper function for time ago
function timeAgo($datetime) {
    if (empty($datetime)) {
        return 'Just now';
    }
    
    $time = strtotime($datetime);
    $time_difference = time() - $time;
    
    if ($time_difference < 1) { 
        return 'just now'; 
    }
    
    $condition = [
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60 => 'month',
        24 * 60 * 60 => 'day',
        60 * 60 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
    
    return 'Just now';
}
?>

<?php 
// Try to include footer, but don't break if it fails
try {
    require_once 'includes/footer.php';
} catch (Exception $e) {
    echo '</body></html>';
}
?>