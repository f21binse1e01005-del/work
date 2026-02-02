<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

try {
    // Get enrolled courses with proper error handling
    $stmt = $db->prepare("SELECT 
            b.batch_id, 
            b.batch_name, 
            c.course_name, 
            b.start_date, 
            b.end_date, 
            ea.application_date,
            b.course_id
        FROM enrollment_applications ea 
        JOIN batches b ON ea.batch_id = b.batch_id 
        JOIN courses c ON b.course_id = c.course_id 
        WHERE ea.user_id = ? 
        AND ea.application_status = 'approved'
        ORDER BY b.start_date DESC");
    
    $stmt->execute([$user_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate progress for each course with safety checks
    $progress_data = [];
    $current_time = time();
    
    foreach ($courses as $course) {
        $start_date = strtotime($course['start_date']);
        $end_date = strtotime($course['end_date']);
        
        // Handle date validation
        if ($start_date && $end_date && $end_date > $start_date) {
            $total_days = ($end_date - $start_date) / (60 * 60 * 24);
            $elapsed_days = ($current_time - $start_date) / (60 * 60 * 24);
            
            // Ensure progress is between 0 and 100
            if ($total_days > 0) {
                $progress = ($elapsed_days / $total_days) * 100;
                $progress = max(0, min(100, $progress));
            } else {
                $progress = 0;
            }
        } else {
            $progress = 0;
        }
        
        $progress_data[$course['batch_id']] = round($progress, 1);
    }
    
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Dashboard SQL Error: " . $e->getMessage());
    $courses = [];
    $progress_data = [];
}
?>

<div class="container-fluid px-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
        <p class="text-muted">Welcome back! Here's your learning overview.</p>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-primary border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Enrolled Courses
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo count($courses); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-success border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                Active Courses
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php 
                                $active_courses = 0;
                                foreach ($courses as $course) {
                                    $start = strtotime($course['start_date']);
                                    $end = strtotime($course['end_date']);
                                    $now = time();
                                    if ($now >= $start && $now <= $end) {
                                        $active_courses++;
                                    }
                                }
                                echo $active_courses;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-play-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-info border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                Classmates
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <a href="classmates.php" class="text-decoration-none text-gray-800">View</a>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-warning border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                Average Progress
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php 
                                if (!empty($progress_data)) {
                                    $avg_progress = array_sum($progress_data) / count($progress_data);
                                    echo round($avg_progress, 1) . '%';
                                } else {
                                    echo '0%';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>My Enrolled Courses</h5>
                <a href="available-courses.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i> Find More Courses
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($courses)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No courses enrolled yet</h5>
                    <p class="text-muted mb-4">Start your learning journey by enrolling in available courses</p>
                    <a href="available-courses.php" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Browse Courses
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course Name</th>
                                <th>Batch</th>
                                <th>Duration</th>
                                <th>Enrollment Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): 
                                $progress = $progress_data[$course['batch_id']] ?? 0;
                                $start_date = strtotime($course['start_date']);
                                $end_date = strtotime($course['end_date']);
                                $now = time();
                                
                                // Determine course status
                                if ($now < $start_date) {
                                    $status = 'Upcoming';
                                    $status_class = 'warning';
                                } elseif ($now > $end_date) {
                                    $status = 'Completed';
                                    $status_class = 'success';
                                } else {
                                    $status = 'In Progress';
                                    $status_class = 'primary';
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($course['batch_name']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', $start_date); ?> - 
                                        <?php echo date('M j, Y', $end_date); ?>
                                    </small>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($course['application_date'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $progress; ?>%"
                                                 aria-valuenow="<?php echo $progress; ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <span class="text-nowrap"><?php echo $progress; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="course-details.php?batch_id=<?php echo $course['batch_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($courses)): ?>
                            <?php 
                            // Show last 3 enrolled courses as recent activity
                            $recent_courses = array_slice($courses, 0, 3);
                            foreach ($recent_courses as $course): 
                            ?>
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-2">
                                            <i class="fas fa-book text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="mb-1">
                                            Enrolled in <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                        </p>
                                        <small class="text-muted">
                                            <i class="far fa-clock me-1"></i>
                                            <?php 
                                            echo date('F j, Y', strtotime($course['application_date']));
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Progress Overview</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($progress_data)): ?>
                        <canvas id="progressChart" height="200"></canvas>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('progressChart').getContext('2d');
                                const progressChart = new Chart(ctx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Completed', 'In Progress', 'Upcoming'],
                                        datasets: [{
                                            data: [
                                                <?php 
                                                $completed = 0;
                                                $in_progress = 0;
                                                $upcoming = 0;
                                                
                                                foreach ($courses as $course) {
                                                    $start = strtotime($course['start_date']);
                                                    $end = strtotime($course['end_date']);
                                                    $now = time();
                                                    
                                                    if ($now > $end) {
                                                        $completed++;
                                                    } elseif ($now >= $start && $now <= $end) {
                                                        $in_progress++;
                                                    } else {
                                                        $upcoming++;
                                                    }
                                                }
                                                
                                                echo $completed . ', ' . $in_progress . ', ' . $upcoming;
                                                ?>
                                            ],
                                            backgroundColor: [
                                                '#28a745',
                                                '#007bff',
                                                '#ffc107'
                                            ],
                                            borderWidth: 2,
                                            borderColor: '#fff'
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    padding: 20,
                                                    usePointStyle: true
                                                }
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No progress data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>