<?php
$pageTitle = 'Progress';
require_once 'includes/header.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : null;

// Get enrolled classes (batches)
$stmt = $db->prepare("
    SELECT DISTINCT 
        b.batch_id, 
        b.batch_name, 
        b.batch_code,
        b.start_date, 
        b.end_date,
        b.status,
        c.course_id,
        c.course_name,
        c.course_code,
        u.full_name as teacher_name,
        COUNT(DISTINCT sqa.quiz_id) as quiz_attempts_count,
        COUNT(DISTINCT asub.assignment_id) as assignments_count
    FROM enrollment_applications ea 
    INNER JOIN batches b ON ea.batch_id = b.batch_id 
    INNER JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN users u ON b.teacher_id = u.user_id
    LEFT JOIN student_quiz_attempts sqa ON sqa.student_id = ea.user_id
    LEFT JOIN assignment_submissions asub ON asub.student_id = ea.user_id
    WHERE ea.user_id = ? 
    AND ea.application_status = 'approved'
    AND b.status IN ('ongoing', 'completed')
    GROUP BY b.batch_id, b.batch_name, b.batch_code, b.start_date, b.end_date, 
             b.status, c.course_id, c.course_name, c.course_code, u.full_name
    ORDER BY b.start_date DESC, b.batch_name ASC
");
$stmt->execute([$user_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get progress data if batch selected
$progress_data = [];
$selected_class = null;

if ($batch_id) {
    // Validate the student is enrolled in this batch
    $stmt = $db->prepare("
        SELECT ea.*, b.*, c.course_name, c.course_code
        FROM enrollment_applications ea
        INNER JOIN batches b ON ea.batch_id = b.batch_id
        INNER JOIN courses c ON b.course_id = c.course_id
        WHERE ea.user_id = ? 
        AND ea.batch_id = ?
        AND ea.application_status = 'approved'
        LIMIT 1
    ");
    $stmt->execute([$user_id, $batch_id]);
    $selected_class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_class) {
        // Get quiz attempts for this batch
        $stmt = $db->prepare("
            SELECT 
                sqa.*,
                q.quiz_id,
                q.quiz_title,
                q.total_points,
                q.time_limit_minutes,
                l.lesson_title,
                m.module_name,
                ROUND((sqa.score / sqa.total_questions) * 100, 2) as percentage_score
            FROM student_quiz_attempts sqa
            INNER JOIN quizzes q ON sqa.quiz_id = q.quiz_id
            INNER JOIN lessons l ON q.lesson_id = l.lesson_id
            INNER JOIN modules m ON l.module_id = m.module_id
            INNER JOIN batches b ON m.course_id = b.course_id
            WHERE sqa.student_id = ?
            AND b.batch_id = ?
            ORDER BY sqa.attempt_date DESC
        ");
        $stmt->execute([$user_id, $batch_id]);
        $progress_data['quizzes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get assignment submissions with grades for this batch
        $stmt = $db->prepare("
            SELECT 
                asub.*,
                a.assignment_id,
                a.assignment_title,
                a.description,
                a.max_points,
                a.due_date,
                a.created_at as assignment_created,
                ROUND((asub.grade / a.max_points) * 100, 2) as percentage_grade
            FROM assignment_submissions asub
            INNER JOIN assignments a ON asub.assignment_id = a.assignment_id
            INNER JOIN batches b ON a.batch_id = b.batch_id
            WHERE asub.student_id = ?
            AND b.batch_id = ?
            ORDER BY asub.submitted_at DESC
        ");
        $stmt->execute([$user_id, $batch_id]);
        $progress_data['assignments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get overall course progress
        $start_date = strtotime($selected_class['start_date']);
        $end_date = strtotime($selected_class['end_date']);
        $current_date = time();
        
        // Calculate date-based progress
        $total_days = max(1, ($end_date - $start_date) / (60 * 60 * 24));
        $elapsed_days = max(0, ($current_date - $start_date) / (60 * 60 * 24));
        $date_progress = min(100, max(0, ($elapsed_days / $total_days) * 100));
        
        // Calculate quiz progress
        $quiz_progress = 0;
        if (!empty($progress_data['quizzes'])) {
            $quiz_scores = array_column($progress_data['quizzes'], 'percentage_score');
            $quiz_progress = array_sum($quiz_scores) / count($quiz_scores);
        }
        
        // Calculate assignment progress
        $assignment_progress = 0;
        if (!empty($progress_data['assignments'])) {
            $submitted_assignments = array_filter($progress_data['assignments'], function($a) {
                return $a['submitted_at'] !== null;
            });
            
            if (!empty($submitted_assignments)) {
                $assignment_scores = array_column($submitted_assignments, 'percentage_grade');
                $assignment_progress = array_sum($assignment_scores) / count($assignment_scores);
            }
        }
        
        // Calculate overall progress (weighted average)
        $weights = [
            'date' => 0.2,
            'quiz' => 0.4,
            'assignment' => 0.4
        ];
        
        $progress_data['overall'] = (
            ($date_progress * $weights['date']) +
            ($quiz_progress * $weights['quiz']) +
            ($assignment_progress * $weights['assignment'])
        );
        
        $progress_data['metrics'] = [
            'date_progress' => $date_progress,
            'quiz_progress' => $quiz_progress,
            'assignment_progress' => $assignment_progress,
            'quiz_count' => count($progress_data['quizzes']),
            'assignment_count' => count($progress_data['assignments'])
        ];
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-chart-line text-primary me-2"></i>Progress & Grades</h1>
                    <p class="text-muted mb-0">Track your academic performance and learning progress</p>
                </div>
                <?php if ($batch_id && $selected_class): ?>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <i class="fas fa-graduation-cap me-1"></i> <?php echo htmlspecialchars($selected_class['batch_code']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Class Selection Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="card-title mb-1">
                                <i class="fas fa-filter text-primary me-2"></i>Select Class/Batch
                            </h5>
                            <p class="text-muted small mb-0">Choose a class to view detailed progress and grades</p>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select form-select-lg" id="classSelector" onchange="onClassChange(this)">
                                <option value="">-- Select a Class --</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['batch_id']; ?>" 
                                        <?php echo $batch_id == $class['batch_id'] ? 'selected' : ''; ?>
                                        data-code="<?php echo htmlspecialchars($class['batch_code']); ?>"
                                        data-course="<?php echo htmlspecialchars($class['course_name']); ?>">
                                    <?php echo htmlspecialchars($class['batch_name']); ?> 
                                    (<?php echo htmlspecialchars($class['course_code']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!$batch_id): ?>
            <!-- Initial State - No Class Selected -->
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-chart-bar fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">Select a Class to Begin</h4>
                        <p class="text-muted mb-4">Choose a class from the dropdown above to view your progress, grades, and learning statistics.</p>
                        <div class="d-flex justify-content-center">
                            <div class="text-start text-muted">
                                <p class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> View quiz scores and attempts</p>
                                <p class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Check assignment grades</p>
                                <p class="mb-0"><i class="fas fa-check-circle text-success me-2"></i> Track overall learning progress</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif (!$selected_class): ?>
            <!-- Invalid Batch Selected -->
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
                        <h4 class="text-warning mb-3">Access Restricted</h4>
                        <p class="text-muted mb-4">You are not enrolled in the selected class or your enrollment is not approved.</p>
                        <a href="progress.php" class="btn btn-primary px-4">
                            <i class="fas fa-arrow-left me-1"></i> Back to Class Selection
                        </a>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Progress Dashboard -->
            <!-- Overall Progress Stats -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                        Overall Progress
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo round($progress_data['overall'], 1); ?>%</div>
                                    <div class="mt-2">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                 style="width: <?php echo $progress_data['overall']; ?>%"
                                                 aria-valuenow="<?php echo $progress_data['overall']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-trophy fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                        Quiz Performance
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo round($progress_data['metrics']['quiz_progress'], 1); ?>%</div>
                                    <div class="text-muted small">
                                        <?php echo $progress_data['metrics']['quiz_count']; ?> attempt(s)
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-check fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                        Assignment Score
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo round($progress_data['metrics']['assignment_progress'], 1); ?>%</div>
                                    <div class="text-muted small">
                                        <?php echo $progress_data['metrics']['assignment_count']; ?> submitted
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tasks fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                        Course Timeline
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo round($progress_data['metrics']['date_progress'], 1); ?>%</div>
                                    <div class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($selected_class['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($selected_class['end_date'])); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Class Information -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        Class Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Batch Name:</td>
                                    <td><strong><?php echo htmlspecialchars($selected_class['batch_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Batch Code:</td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($selected_class['batch_code']); ?></span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Course:</td>
                                    <td><?php echo htmlspecialchars($selected_class['course_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Status:</td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'upcoming' => 'bg-info',
                                            'ongoing' => 'bg-success',
                                            'completed' => 'bg-secondary',
                                            'cancelled' => 'bg-danger'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $status_badge[$selected_class['status']] ?? 'bg-secondary'; ?>">
                                            <?php echo ucfirst($selected_class['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Duration:</td>
                                    <td>
                                        <?php 
                                        $start = new DateTime($selected_class['start_date']);
                                        $end = new DateTime($selected_class['end_date']);
                                        $interval = $start->diff($end);
                                        echo $interval->format('%m months %d days');
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Teacher:</td>
                                    <td><?php echo htmlspecialchars($selected_class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Progress Sections -->
            <div class="row">
                <!-- Quiz Results -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-check text-success me-2"></i>
                                Quiz Results
                            </h5>
                            <span class="badge bg-success"><?php echo count($progress_data['quizzes']); ?> Attempts</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($progress_data['quizzes'])): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-question fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No Quiz Attempts Yet</h6>
                                <p class="text-muted small">Quiz attempts will appear here once you complete quizzes.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Quiz</th>
                                            <th>Score</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($progress_data['quizzes'] as $quiz): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong class="mb-1"><?php echo htmlspecialchars($quiz['quiz_title']); ?></strong>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($quiz['module_name']); ?> &raquo; 
                                                        <?php echo htmlspecialchars($quiz['lesson_title']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column align-items-center">
                                                    <span class="fw-bold"><?php echo $quiz['percentage_score']; ?>%</span>
                                                    <small class="text-muted"><?php echo $quiz['score']; ?>/<?php echo $quiz['total_questions']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($quiz['attempt_date'])); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($quiz['attempt_date'])); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $score_percentage = $quiz['percentage_score'];
                                                if ($score_percentage >= 80) {
                                                    $badge_class = 'bg-success';
                                                    $status_text = 'Excellent';
                                                } elseif ($score_percentage >= 60) {
                                                    $badge_class = 'bg-info';
                                                    $status_text = 'Good';
                                                } elseif ($score_percentage >= 40) {
                                                    $badge_class = 'bg-warning';
                                                    $status_text = 'Average';
                                                } else {
                                                    $badge_class = 'bg-danger';
                                                    $status_text = 'Needs Improvement';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Assignment Grades -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-tasks text-info me-2"></i>
                                Assignment Grades
                            </h5>
                            <span class="badge bg-info"><?php echo count($progress_data['assignments']); ?> Submitted</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($progress_data['assignments'])): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No Assignments Submitted</h6>
                                <p class="text-muted small">Assignment submissions will appear here once you submit assignments.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Assignment</th>
                                            <th>Grade</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($progress_data['assignments'] as $assignment): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong class="mb-1"><?php echo htmlspecialchars($assignment['assignment_title']); ?></strong>
                                                    <small class="text-muted">
                                                        <?php echo substr(htmlspecialchars($assignment['description']), 0, 50); ?>
                                                        <?php if (strlen($assignment['description']) > 50): ?>...<?php endif; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($assignment['grade'] !== null): ?>
                                                <div class="d-flex flex-column align-items-center">
                                                    <span class="fw-bold"><?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?></span>
                                                    <small class="text-muted"><?php echo $assignment['percentage_grade']; ?>%</small>
                                                </div>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($assignment['due_date']): ?>
                                                <small><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></small>
                                                <?php if (strtotime($assignment['due_date']) < time() && $assignment['grade'] === null): ?>
                                                <br>
                                                <small class="text-danger">Overdue</small>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($assignment['grade'] !== null): ?>
                                                <?php
                                                $grade_percentage = $assignment['percentage_grade'];
                                                if ($grade_percentage >= 80) {
                                                    $badge_class = 'bg-success';
                                                    $status_text = 'Graded';
                                                } elseif ($grade_percentage >= 60) {
                                                    $badge_class = 'bg-info';
                                                    $status_text = 'Graded';
                                                } elseif ($grade_percentage >= 40) {
                                                    $badge_class = 'bg-warning';
                                                    $status_text = 'Graded';
                                                } else {
                                                    $badge_class = 'bg-danger';
                                                    $status_text = 'Graded';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Pending Review</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Progress Chart (Placeholder for Chart.js) -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Progress Trend
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="bg-light rounded p-4" style="height: 300px;">
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <div class="text-center">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Progress chart visualization would appear here</p>
                                        <p class="text-muted small">(Requires Chart.js integration)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="h-100 d-flex flex-column justify-content-center">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">Progress Breakdown</h6>
                                    <div class="mb-3">
                                        <small class="d-block text-muted mb-1">Quiz Performance</small>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $progress_data['metrics']['quiz_progress']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round($progress_data['metrics']['quiz_progress'], 1); ?>%</small>
                                    </div>
                                    <div class="mb-3">
                                        <small class="d-block text-muted mb-1">Assignment Score</small>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar bg-info" 
                                                 style="width: <?php echo $progress_data['metrics']['assignment_progress']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round($progress_data['metrics']['assignment_progress'], 1); ?>%</small>
                                    </div>
                                    <div>
                                        <small class="d-block text-muted mb-1">Timeline Progress</small>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?php echo $progress_data['metrics']['date_progress']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round($progress_data['metrics']['date_progress'], 1); ?>%</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
function onClassChange(select) {
    const selectedValue = select.value;
    if (selectedValue) {
        window.location.href = 'progress.php?batch_id=' + selectedValue;
    } else {
        window.location.href = 'progress.php';
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add animation to progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, 100);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>