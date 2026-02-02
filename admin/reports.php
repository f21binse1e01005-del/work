<?php
/**
 * Admin Reports
 * File: admin/reports.php
 */

require_once 'includes/header.php';

// Get report data
$enrollmentStats = $db->query("
    SELECT 
        DATE_FORMAT(application_date, '%Y-%m') as month,
        COUNT(*) as applications,
        SUM(CASE WHEN application_status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM enrollment_applications 
    WHERE application_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(application_date, '%Y-%m')
    ORDER BY month
")->fetchAll(PDO::FETCH_ASSOC);

$courseStats = $db->query("
    SELECT c.course_name, COUNT(ea.application_id) as enrollments,
           AVG(CASE WHEN e.final_grade IS NOT NULL THEN e.final_grade END) as avg_grade
    FROM courses c
    LEFT JOIN enrollment_applications ea ON c.course_id = ea.course_id AND ea.application_status = 'approved'
    LEFT JOIN enrollments e ON ea.application_id = e.application_id
    WHERE c.is_active = 1
    GROUP BY c.course_id
    ORDER BY enrollments DESC
")->fetchAll(PDO::FETCH_ASSOC);

$revenueStats = $db->query("
    SELECT 
        DATE_FORMAT(application_date, '%Y-%m') as month,
        SUM(CASE WHEN payment_status = 'paid' THEN payment_amount ELSE 0 END) as revenue
    FROM enrollment_applications 
    WHERE application_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(application_date, '%Y-%m')
    ORDER BY month
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1>
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print
            </button>
            <button class="btn btn-outline-success">
                <i class="fas fa-download me-1"></i>Export PDF
            </button>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-line me-2"></i>Enrollment Trends</h6>
                </div>
                <div class="card-body">
                    <canvas id="enrollmentChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-money-bill-wave me-2"></i>Revenue Trends</h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-book me-2"></i>Course Performance</h6>
                </div>
                <div class="card-body">
                    <canvas id="courseChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6>Course Enrollment Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Enrollments</th>
                                    <th>Avg Grade</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($courseStats as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo $course['enrollments']; ?></td>
                                        <td>
                                            <?php if ($course['avg_grade']): ?>
                                                <?php echo number_format($course['avg_grade'], 1); ?>%
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($course['avg_grade']): ?>
                                                <?php if ($course['avg_grade'] >= 80): ?>
                                                    <span class="badge bg-success">Excellent</span>
                                                <?php elseif ($course['avg_grade'] >= 70): ?>
                                                    <span class="badge bg-info">Good</span>
                                                <?php elseif ($course['avg_grade'] >= 60): ?>
                                                    <span class="badge bg-warning">Average</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Needs Improvement</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Data</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>Quick Reports</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="generateReport('student-list')">
                            <i class="fas fa-users me-2"></i>Student List
                        </button>
                        <button class="btn btn-outline-success" onclick="generateReport('teacher-report')">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Report
                        </button>
                        <button class="btn btn-outline-info" onclick="generateReport('financial')">
                            <i class="fas fa-chart-pie me-2"></i>Financial Report
                        </button>
                        <button class="btn btn-outline-warning" onclick="generateReport('attendance')">
                            <i class="fas fa-calendar-check me-2"></i>Attendance Report
                        </button>
                        <button class="btn btn-outline-secondary" onclick="generateReport('certificate')">
                            <i class="fas fa-certificate me-2"></i>Certificate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Enrollment Chart
const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
new Chart(enrollmentCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($enrollmentStats, 'month')); ?>,
        datasets: [{
            label: 'Applications',
            data: <?php echo json_encode(array_column($enrollmentStats, 'applications')); ?>,
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.4
        }, {
            label: 'Approved',
            data: <?php echo json_encode(array_column($enrollmentStats, 'approved')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
    }
});

// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($revenueStats, 'month')); ?>,
        datasets: [{
            label: 'Revenue (Rs.)',
            data: <?php echo json_encode(array_column($revenueStats, 'revenue')); ?>,
            backgroundColor: 'rgba(255, 99, 132, 0.8)',
            borderColor: 'rgb(255, 99, 132)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
    }
});

// Course Performance Chart
const courseCtx = document.getElementById('courseChart').getContext('2d');
new Chart(courseCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_slice(array_column($courseStats, 'course_name'), 0, 5)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_slice(array_column($courseStats, 'enrollments'), 0, 5)); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

function generateReport(type) {
    window.open(`generate-report.php?type=${type}`, '_blank');
}
</script>

<?php require_once 'includes/footer.php'; ?>