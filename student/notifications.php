<?php
$pageTitle = 'Notifications';
require_once 'includes/header.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Get enrolled batch IDs for the student
$stmt = $db->prepare("
    SELECT DISTINCT ea.batch_id, b.batch_name, b.batch_code, c.course_name
    FROM enrollment_applications ea
    INNER JOIN batches b ON ea.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ea.user_id = ? 
    AND ea.application_status = 'approved'
    AND b.status IN ('ongoing', 'upcoming')
    ORDER BY b.start_date DESC
");
$stmt->execute([$user_id]);
$enrolled_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
$batch_ids = array_column($enrolled_batches, 'batch_id');

// Mark notifications as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
}

// Get unread notifications count
$stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_data = $stmt->fetch(PDO::FETCH_ASSOC);
$unread_count = $unread_data['unread_count'] ?? 0;

// Get personal notifications
$stmt = $db->prepare("
    SELECT n.*,
           CASE 
               WHEN n.type = 'announcement' THEN 'fas fa-bullhorn'
               WHEN n.type = 'assignment' THEN 'fas fa-tasks'
               WHEN n.type = 'event' THEN 'fas fa-calendar'
               WHEN n.type = 'quiz' THEN 'fas fa-clipboard-check'
               WHEN n.type = 'grade' THEN 'fas fa-star'
               WHEN n.type = 'system' THEN 'fas fa-cog'
               ELSE 'fas fa-bell'
           END as icon_class,
           CASE 
               WHEN n.type = 'announcement' THEN 'bg-primary'
               WHEN n.type = 'assignment' THEN 'bg-warning'
               WHEN n.type = 'event' THEN 'bg-success'
               WHEN n.type = 'quiz' THEN 'bg-info'
               WHEN n.type = 'grade' THEN 'bg-purple'
               WHEN n.type = 'system' THEN 'bg-secondary'
               ELSE 'bg-dark'
           END as badge_class
    FROM notifications n
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC, n.is_read ASC
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get announcements for enrolled batches
$announcements = [];
if (!empty($batch_ids)) {
    $placeholders = str_repeat('?,', count($batch_ids) - 1) . '?';
    $stmt = $db->prepare("
        SELECT a.*, 
               b.batch_name,
               b.batch_code,
               u.full_name as author_name,
               u.profile_image as author_image,
               CASE 
                   WHEN DATEDIFF(NOW(), a.created_at) = 0 THEN 'Today'
                   WHEN DATEDIFF(NOW(), a.created_at) = 1 THEN 'Yesterday'
                   WHEN DATEDIFF(NOW(), a.created_at) < 7 THEN CONCAT(DATEDIFF(NOW(), a.created_at), ' days ago')
                   ELSE DATE_FORMAT(a.created_at, '%b %d, %Y')
               END as time_ago
        FROM announcements a 
        INNER JOIN batches b ON a.batch_id = b.batch_id 
        INNER JOIN users u ON a.created_by = u.user_id 
        WHERE a.batch_id IN ($placeholders) 
        AND a.is_published = 1
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute($batch_ids);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get upcoming events for enrolled batches
$events = [];
if (!empty($batch_ids)) {
    $stmt = $db->prepare("
        SELECT e.*, 
               b.batch_name,
               b.batch_code,
               CASE 
                   WHEN e.event_date = CURDATE() THEN 'Today'
                   WHEN e.event_date = CURDATE() + INTERVAL 1 DAY THEN 'Tomorrow'
                   ELSE DATE_FORMAT(e.event_date, '%b %d')
               END as display_date,
               CASE 
                   WHEN e.event_date < CURDATE() THEN 'past'
                   WHEN e.event_date = CURDATE() THEN 'today'
                   WHEN e.event_date = CURDATE() + INTERVAL 1 DAY THEN 'tomorrow'
                   ELSE 'upcoming'
               END as event_status
        FROM calendar_events e 
        INNER JOIN batches b ON e.batch_id = b.batch_id 
        WHERE e.batch_id IN ($placeholders) 
        AND e.event_date >= CURDATE() - INTERVAL 1 DAY
        ORDER BY e.event_date ASC, e.event_time ASC
        LIMIT 10
    ");
    $stmt->execute($batch_ids);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get upcoming assignments with submission status
$assignments = [];
if (!empty($batch_ids)) {
    $stmt = $db->prepare("
        SELECT a.*, 
               b.batch_name,
               b.batch_code,
               c.course_name,
               COALESCE(asub.submitted_at, NULL) as submitted_at,
               asub.grade,
               asub.feedback,
               CASE 
                   WHEN asub.submitted_at IS NOT NULL THEN 'submitted'
                   WHEN a.due_date < CURDATE() THEN 'overdue'
                   WHEN a.due_date = CURDATE() THEN 'due_today'
                   WHEN a.due_date = CURDATE() + INTERVAL 1 DAY THEN 'due_tomorrow'
                   ELSE 'upcoming'
               END as assignment_status,
               DATEDIFF(a.due_date, CURDATE()) as days_remaining
        FROM assignments a 
        INNER JOIN batches b ON a.batch_id = b.batch_id 
        INNER JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN assignment_submissions asub ON a.assignment_id = asub.assignment_id 
            AND asub.student_id = ?
        WHERE a.batch_id IN ($placeholders) 
        AND a.is_published = 1
        AND (a.due_date >= CURDATE() - INTERVAL 7 DAY OR asub.submitted_at IS NULL)
        ORDER BY a.due_date ASC, a.created_at DESC
        LIMIT 10
    ");
    $params = array_merge([$user_id], $batch_ids);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get upcoming quizzes
$quizzes = [];
if (!empty($batch_ids)) {
    $stmt = $db->prepare("
        SELECT DISTINCT q.*,
               b.batch_name,
               b.batch_code,
               c.course_name,
               l.lesson_title,
               m.module_name,
               COALESCE(sqa.attempt_count, 0) as attempt_count,
               sqa.max_score,
               CASE 
                   WHEN q.due_date < CURDATE() THEN 'past_due'
                   WHEN q.publish_date > CURDATE() THEN 'upcoming'
                   WHEN COALESCE(sqa.attempt_count, 0) >= q.max_attempts THEN 'attempted_max'
                   ELSE 'available'
               END as quiz_status,
               CASE 
                   WHEN q.due_date = CURDATE() THEN 'Due Today'
                   WHEN q.due_date = CURDATE() + INTERVAL 1 DAY THEN 'Due Tomorrow'
                   WHEN q.due_date IS NOT NULL THEN CONCAT('Due in ', DATEDIFF(q.due_date, CURDATE()), ' days')
                   ELSE 'No due date'
               END as due_display
        FROM quizzes q
        INNER JOIN lessons l ON q.lesson_id = l.lesson_id
        INNER JOIN modules m ON l.module_id = m.module_id
        INNER JOIN batches b ON m.course_id = b.course_id
        INNER JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN (
            SELECT quiz_id, student_id,
                   COUNT(*) as attempt_count,
                   MAX(percentage) as max_score
            FROM student_quiz_attempts
            WHERE student_id = ?
            GROUP BY quiz_id, student_id
        ) sqa ON q.quiz_id = sqa.quiz_id
        WHERE b.batch_id IN ($placeholders)
        AND q.is_published = 1
        AND (q.due_date >= CURDATE() - INTERVAL 7 DAY OR q.due_date IS NULL)
        ORDER BY q.due_date ASC, q.publish_date DESC
        LIMIT 10
    ");
    $params = array_merge([$user_id], $batch_ids);
    $stmt->execute($params);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate statistics
$pending_assignments = array_filter($assignments, function ($a) {
    return $a['submitted_at'] === null && $a['assignment_status'] !== 'past';
});
$overdue_assignments = array_filter($assignments, function ($a) {
    return $a['submitted_at'] === null && $a['assignment_status'] === 'overdue';
});
$upcoming_quizzes = array_filter($quizzes, function ($q) {
    return in_array($q['quiz_status'], ['available', 'upcoming']);
});
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-bell text-primary me-2"></i>Notifications Center</h1>
                    <p class="text-muted mb-0">Stay updated with your academic activities and announcements</p>
                </div>
                <div class="d-flex align-items-center">
                    <?php if ($unread_count > 0): ?>
                        <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                            <i class="fas fa-envelope me-1"></i> Personal Notifications
                            <span class="badge bg-danger ms-1"><?php echo $unread_count; ?> unread</span>
                        </button>
                    <?php endif; ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-secondary"
                            <?php echo $unread_count == 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-double me-1"></i> Mark All as Read
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                        Announcements
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo count($announcements); ?></div>
                                    <div class="mt-2">
                                        <span class="badge bg-primary">New</span>
                                        <span class="text-muted small ms-2">
                                            <?php
                                            $recent_announcements = array_filter($announcements, function ($a) {
                                                return strtotime($a['created_at']) > strtotime('-24 hours');
                                            });
                                            echo count($recent_announcements);
                                            ?> in last 24h
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-bullhorn fa-2x text-primary"></i>
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
                                        Pending Assignments
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo count($pending_assignments); ?></div>
                                    <div class="mt-2">
                                        <?php if (count($overdue_assignments) > 0): ?>
                                            <span class="badge bg-danger"><?php echo count($overdue_assignments); ?> Overdue</span>
                                        <?php endif; ?>
                                        <span class="text-muted small ms-2">
                                            <?php
                                            $due_today = array_filter($pending_assignments, function ($a) {
                                                return $a['assignment_status'] === 'due_today';
                                            });
                                            echo count($due_today);
                                            ?> due today
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tasks fa-2x text-warning"></i>
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
                                        Upcoming Events
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo count($events); ?></div>
                                    <div class="mt-2">
                                        <?php
                                        $today_events = array_filter($events, function ($e) {
                                            return $e['event_status'] === 'today';
                                        });
                                        if (count($today_events) > 0): ?>
                                            <span class="badge bg-success"><?php echo count($today_events); ?> Today</span>
                                        <?php endif; ?>
                                        <span class="text-muted small ms-2">
                                            Next: <?php echo !empty($events) ? $events[0]['display_date'] : 'None'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt fa-2x text-success"></i>
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
                                        Upcoming Quizzes
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?php echo count($upcoming_quizzes); ?></div>
                                    <div class="mt-2">
                                        <?php
                                        $available_quizzes = array_filter($upcoming_quizzes, function ($q) {
                                            return $q['quiz_status'] === 'available';
                                        });
                                        if (count($available_quizzes) > 0): ?>
                                            <span class="badge bg-info"><?php echo count($available_quizzes); ?> Available</span>
                                        <?php endif; ?>
                                        <span class="text-muted small ms-2">
                                            Next: <?php echo !empty($upcoming_quizzes) ? $upcoming_quizzes[0]['due_display'] : 'None'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-check fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Tabs -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom-0 pt-3">
                            <ul class="nav nav-tabs card-header-tabs" id="notificationsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="announcements-tab" data-bs-toggle="tab"
                                        data-bs-target="#announcements" type="button" role="tab">
                                        <i class="fas fa-bullhorn me-2"></i>Announcements
                                        <?php if (count($announcements) > 0): ?>
                                            <span class="badge bg-primary rounded-pill ms-1"><?php echo count($announcements); ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="assignments-tab" data-bs-toggle="tab"
                                        data-bs-target="#assignments" type="button" role="tab">
                                        <i class="fas fa-tasks me-2"></i>Assignments
                                        <?php if (count($pending_assignments) > 0): ?>
                                            <span class="badge bg-warning rounded-pill ms-1"><?php echo count($pending_assignments); ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="events-tab" data-bs-toggle="tab"
                                        data-bs-target="#events" type="button" role="tab">
                                        <i class="fas fa-calendar-alt me-2"></i>Events
                                        <?php if (count($events) > 0): ?>
                                            <span class="badge bg-success rounded-pill ms-1"><?php echo count($events); ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="quizzes-tab" data-bs-toggle="tab"
                                        data-bs-target="#quizzes" type="button" role="tab">
                                        <i class="fas fa-clipboard-check me-2"></i>Quizzes
                                        <?php if (count($upcoming_quizzes) > 0): ?>
                                            <span class="badge bg-info rounded-pill ms-1"><?php echo count($upcoming_quizzes); ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="notificationsTabContent">
                                <!-- Announcements Tab -->
                                <div class="tab-pane fade show active" id="announcements" role="tabpanel">
                                    <?php if (empty($announcements)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted mb-3">No Announcements</h5>
                                            <p class="text-muted">Your instructors haven't posted any announcements yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($announcements as $announcement): ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                    <div class="card h-100 border-0 shadow-sm">
                                                        <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <span class="badge bg-primary bg-opacity-10 text-primary mb-2">
                                                                        <?php echo htmlspecialchars($announcement['batch_code']); ?>
                                                                    </span>
                                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                                </div>
                                                                <span class="badge bg-light text-dark"><?php echo $announcement['time_ago']; ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="card-body pt-2">
                                                            <p class="card-text text-muted small mb-3">
                                                                <?php echo substr(strip_tags($announcement['content']), 0, 120); ?>
                                                                <?php if (strlen(strip_tags($announcement['content'])) > 120): ?>...<?php endif; ?>
                                                            </p>
                                                            <div class="d-flex align-items-center mt-3">
                                                                <?php if ($announcement['author_image']): ?>
                                                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($announcement['author_image']); ?>"
                                                                        alt="<?php echo htmlspecialchars($announcement['author_name']); ?>"
                                                                        class="rounded-circle me-2" width="30" height="30">
                                                                <?php else: ?>
                                                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-2"
                                                                        style="width: 30px; height: 30px;">
                                                                        <i class="fas fa-user"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <small class="text-muted">By <?php echo htmlspecialchars($announcement['author_name']); ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer bg-white border-top-0 pt-0">
                                                            <button class="btn btn-sm btn-outline-primary w-100"
                                                                onclick="viewAnnouncement(<?php echo $announcement['announcement_id']; ?>)">
                                                                <i class="fas fa-eye me-1"></i> View Details
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Assignments Tab -->
                                <div class="tab-pane fade" id="assignments" role="tabpanel">
                                    <?php if (empty($assignments)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted mb-3">No Assignments</h5>
                                            <p class="text-muted">There are no assignments for your enrolled batches.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Assignment</th>
                                                        <th>Course</th>
                                                        <th>Due Date</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($assignments as $assignment): ?>
                                                        <tr class="<?php echo $assignment['assignment_status'] === 'overdue' ? 'table-danger' : ''; ?>">
                                                            <td>
                                                                <div class="d-flex flex-column">
                                                                    <strong class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['batch_code']); ?></small>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
                                                            <td>
                                                                <div class="d-flex flex-column">
                                                                    <span><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></span>
                                                                    <small class="text-muted">
                                                                        <?php if ($assignment['days_remaining'] > 0): ?>
                                                                            <?php echo $assignment['days_remaining']; ?> days remaining
                                                                        <?php elseif ($assignment['days_remaining'] == 0): ?>
                                                                            Due today
                                                                        <?php else: ?>
                                                                            Overdue by <?php echo abs($assignment['days_remaining']); ?> days
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php if ($assignment['submitted_at']): ?>
                                                                    <span class="badge bg-success">
                                                                        <i class="fas fa-check me-1"></i> Submitted
                                                                    </span>
                                                                    <?php if ($assignment['grade'] !== null): ?>
                                                                        <br>
                                                                        <small class="text-muted">Grade: <?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <?php if ($assignment['assignment_status'] === 'overdue'): ?>
                                                                        <span class="badge bg-danger">
                                                                            <i class="fas fa-exclamation-triangle me-1"></i> Overdue
                                                                        </span>
                                                                    <?php elseif ($assignment['assignment_status'] === 'due_today'): ?>
                                                                        <span class="badge bg-warning">
                                                                            <i class="fas fa-clock me-1"></i> Due Today
                                                                        </span>
                                                                    <?php elseif ($assignment['assignment_status'] === 'due_tomorrow'): ?>
                                                                        <span class="badge bg-info">
                                                                            <i class="fas fa-clock me-1"></i> Due Tomorrow
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">
                                                                            <i class="fas fa-clock me-1"></i> Pending
                                                                        </span>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($assignment['submitted_at']): ?>
                                                                    <a href="assignment_submission.php?id=<?php echo $assignment['assignment_id']; ?>"
                                                                        class="btn btn-sm btn-outline-primary">
                                                                        <i class="fas fa-eye me-1"></i> View
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="submit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>"
                                                                        class="btn btn-sm btn-primary">
                                                                        <i class="fas fa-upload me-1"></i> Submit
                                                                    </a>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Events Tab -->
                                <div class="tab-pane fade" id="events" role="tabpanel">
                                    <?php if (empty($events)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted mb-3">No Upcoming Events</h5>
                                            <p class="text-muted">There are no upcoming events scheduled for your batches.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($events as $event): ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                    <div class="card h-100 border-0 shadow-sm">
                                                        <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <span class="badge bg-success bg-opacity-10 text-success mb-2">
                                                                        <?php echo htmlspecialchars($event['batch_code']); ?>
                                                                    </span>
                                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                                </div>
                                                                <?php if ($event['event_status'] === 'today'): ?>
                                                                    <span class="badge bg-success">Today</span>
                                                                <?php elseif ($event['event_status'] === 'tomorrow'): ?>
                                                                    <span class="badge bg-info">Tomorrow</span>
                                                                <?php elseif ($event['event_status'] === 'past'): ?>
                                                                    <span class="badge bg-secondary">Past</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="card-body pt-2">
                                                            <div class="mb-3">
                                                                <p class="mb-1">
                                                                    <i class="fas fa-calendar text-muted me-2"></i>
                                                                    <strong><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?></strong>
                                                                </p>
                                                                <?php if ($event['event_time']): ?>
                                                                    <p class="mb-1">
                                                                        <i class="fas fa-clock text-muted me-2"></i>
                                                                        <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                                <?php if ($event['location']): ?>
                                                                    <p class="mb-1">
                                                                        <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($event['description']): ?>
                                                                <p class="card-text text-muted small mb-3">
                                                                    <?php echo substr(strip_tags($event['description']), 0, 100); ?>
                                                                    <?php if (strlen(strip_tags($event['description'])) > 100): ?>...<?php endif; ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="card-footer bg-white border-top-0 pt-0">
                                                            <?php if ($event['event_status'] === 'past'): ?>
                                                                <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                                                    <i class="fas fa-history me-1"></i> Event Ended
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-outline-success w-100"
                                                                    onclick="addToCalendar(<?php echo $event['event_id']; ?>)">
                                                                    <i class="fas fa-calendar-plus me-1"></i> Add to Calendar
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Quizzes Tab -->
                                <div class="tab-pane fade" id="quizzes" role="tabpanel">
                                    <?php if (empty($quizzes)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted mb-3">No Upcoming Quizzes</h5>
                                            <p class="text-muted">There are no quizzes scheduled for your batches.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Quiz</th>
                                                        <th>Module/Lesson</th>
                                                        <th>Due Date</th>
                                                        <th>Attempts</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($quizzes as $quiz): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex flex-column">
                                                                    <strong class="mb-1"><?php echo htmlspecialchars($quiz['quiz_title']); ?></strong>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($quiz['batch_code']); ?></small>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?php echo htmlspecialchars($quiz['module_name']); ?><br>
                                                                    <i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($quiz['lesson_title']); ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <?php if ($quiz['due_date']): ?>
                                                                    <div class="d-flex flex-column">
                                                                        <span><?php echo date('M d, Y', strtotime($quiz['due_date'])); ?></span>
                                                                        <small class="text-muted"><?php echo $quiz['due_display']; ?></small>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">No due date</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php echo $quiz['attempt_count']; ?>/<?php echo $quiz['max_attempts']; ?>
                                                                <?php if ($quiz['max_score']): ?>
                                                                    <br>
                                                                    <small class="text-success">Best: <?php echo $quiz['max_score']; ?>%</small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($quiz['quiz_status'] === 'attempted_max'): ?>
                                                                    <span class="badge bg-secondary">
                                                                        <i class="fas fa-check-circle me-1"></i> Max Attempts
                                                                    </span>
                                                                <?php elseif ($quiz['quiz_status'] === 'past_due'): ?>
                                                                    <span class="badge bg-danger">
                                                                        <i class="fas fa-exclamation-triangle me-1"></i> Past Due
                                                                    </span>
                                                                <?php elseif ($quiz['quiz_status'] === 'available'): ?>
                                                                    <span class="badge bg-success">
                                                                        <i class="fas fa-play-circle me-1"></i> Available
                                                                    </span>
                                                                <?php elseif ($quiz['quiz_status'] === 'upcoming'): ?>
                                                                    <span class="badge bg-info">
                                                                        <i class="fas fa-clock me-1"></i> Upcoming
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($quiz['quiz_status'] === 'available'): ?>
                                                                    <a href="quizzes.php?take=<?php echo $quiz['quiz_id']; ?>"
                                                                        class="btn btn-sm btn-success">
                                                                        <i class="fas fa-play me-1"></i> Take Quiz
                                                                    </a>
                                                                <?php elseif ($quiz['quiz_status'] === 'attempted_max'): ?>
                                                                    <a href="quiz_results.php?quiz_id=<?php echo $quiz['quiz_id']; ?>"
                                                                        class="btn btn-sm btn-outline-primary">
                                                                        <i class="fas fa-chart-line me-1"></i> View Results
                                                                    </a>
                                                                <?php else: ?>
                                                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                                                        <i class="fas fa-lock me-1"></i> Not Available
                                                                    </button>
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Personal Notifications Modal -->
<div class="modal fade" id="notificationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-envelope me-2"></i>Personal Notifications
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Personal Notifications</h5>
                        <p class="text-muted">You don't have any personal notifications.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item list-group-item-action border-0 <?php echo $notification['is_read'] == 0 ? 'bg-light' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div class="d-flex align-items-start">
                                        <div class="p-2 me-3">
                                            <i class="<?php echo $notification['icon_class']; ?> fa-lg <?php echo str_replace('bg-', 'text-', $notification['badge_class']); ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <p class="mb-1 small text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($notification['is_read'] == 0): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                <button type="submit" name="mark_read" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($notification['action_url']): ?>
                                    <div class="mt-2">
                                        <a href="<?php echo htmlspecialchars($notification['action_url']); ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt me-1"></i> Take Action
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <form method="POST" class="me-auto">
                    <button type="submit" name="mark_all_read" class="btn btn-primary"
                        <?php echo $unread_count == 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-double me-1"></i> Mark All as Read
                    </button>
                </form>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    // View announcement details
    function viewAnnouncement(announcementId) {
        // You can implement a modal or redirect to detailed view
        window.location.href = 'announcement_details.php?id=' + announcementId;
    }

    // Add event to calendar
    function addToCalendar(eventId) {
        // Implement calendar integration
        alert('This would add the event to your calendar. Event ID: ' + eventId);
    }

    // Auto-refresh notifications every 60 seconds
    setTimeout(function() {
        location.reload();
    }, 60000);

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Auto-show notifications modal if there are unread notifications
        <?php if ($unread_count > 0): ?>
            const notificationsModal = new bootstrap.Modal(document.getElementById('notificationsModal'));
            // Uncomment below line to auto-show modal when there are unread notifications
            // notificationsModal.show();
        <?php endif; ?>

        // Tab persistence
        const activeTab = localStorage.getItem('activeNotificationsTab');
        if (activeTab) {
            const tab = new bootstrap.Tab(document.querySelector('#' + activeTab + '-tab'));
            tab.show();
        }

        // Save active tab on change
        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tabEl => {
            tabEl.addEventListener('shown.bs.tab', function(event) {
                localStorage.setItem('activeNotificationsTab', event.target.id.replace('-tab', ''));
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>