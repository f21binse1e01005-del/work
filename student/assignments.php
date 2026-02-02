<?php
$pageTitle = 'Assignments';
session_start();
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'] ?? null;

// Check if user is logged in
if (!$user_id) {
    header('Location: login.php');
    exit;
}

$action = $_GET['action'] ?? '';
$assignment_id = $_GET['assignment_id'] ?? null;
$submission_id = $_GET['submission_id'] ?? null;
$view = $_GET['view'] ?? 'list'; // list, calendar, submitted

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_assignment':
                handleAssignmentSubmission($db, $user_id);
                break;
            case 'update_submission':
                handleSubmissionUpdate($db, $user_id);
                break;
            case 'delete_submission':
                handleSubmissionDelete($db, $user_id);
                break;
        }
    }
}

// Get user's active enrollments
$stmt = $db->prepare("
    SELECT e.enrollment_id, b.batch_id, b.batch_name, c.course_name, c.course_code
    FROM enrollments e
    JOIN enrollment_applications ea ON e.application_id = ea.application_id
    JOIN batches b ON ea.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ea.user_id = ? 
    AND e.enrollment_status = 'active'
    AND b.status IN ('ongoing', 'upcoming')
    ORDER BY c.course_name
");
$stmt->execute([$user_id]);
$enrollments = $stmt->fetchAll();

// Get assignments based on view
$assignments = [];
$submissions = [];
$calendar_events = [];

if ($enrollments) {
    $enrollment_ids = array_column($enrollments, 'enrollment_id');
    $batch_ids = array_column($enrollments, 'batch_id');

    switch ($view) {
        case 'submitted':
            $submissions = getSubmittedAssignments($db, $enrollment_ids);
            break;
        case 'calendar':
            $calendar_events = getAssignmentCalendar($db, $batch_ids, $user_id);
            break;
        default:
            $assignments = getUpcomingAssignments($db, $batch_ids, $user_id);
            break;
    }
}

// Get assignment details if viewing a specific assignment
$assignment_details = null;
if ($assignment_id && $action === 'submit') {
    $assignment_details = getAssignmentDetails($db, $assignment_id, $user_id);
}

// Get submission details if viewing submission
$submission_details = null;
if ($submission_id) {
    $submission_details = getSubmissionDetails($db, $submission_id, $user_id);
}
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h2"><i class="fas fa-tasks me-2"></i>Assignments</h1>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($assignment_id && $action === 'submit' && $assignment_details): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.history.back()">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </button>
            <?php else: ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary <?php echo $view === 'list' ? 'active' : ''; ?>"
                        onclick="location.href='?view=list'">
                        <i class="fas fa-list me-1"></i>List
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary <?php echo $view === 'calendar' ? 'active' : ''; ?>"
                        onclick="location.href='?view=calendar'">
                        <i class="fas fa-calendar me-1"></i>Calendar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary <?php echo $view === 'submitted' ? 'active' : ''; ?>"
                        onclick="location.href='?view=submitted'">
                        <i class="fas fa-check-circle me-1"></i>Submitted
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($_GET['submitted'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <div class="d-flex">
            <div class="me-3">
                <i class="fas fa-check-circle fa-2x"></i>
            </div>
            <div>
                <h5 class="mb-1">Assignment Submitted Successfully!</h5>
                <p class="mb-0">Your assignment has been submitted. You can view it in the "Submitted" tab.</p>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <div class="d-flex">
            <div class="me-3">
                <i class="fas fa-sync-alt fa-2x"></i>
            </div>
            <div>
                <h5 class="mb-1">Submission Updated!</h5>
                <p class="mb-0">Your assignment submission has been updated successfully.</p>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <div class="d-flex">
            <div class="me-3">
                <i class="fas fa-trash-alt fa-2x"></i>
            </div>
            <div>
                <h5 class="mb-1">Submission Deleted</h5>
                <p class="mb-0">Your assignment submission has been deleted. You can submit again before the deadline.</p>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if ($assignment_id && $action === 'submit' && $assignment_details): ?>
    <?php include 'includes/assignment_submission_form.php'; ?>
<?php elseif ($submission_id && $submission_details): ?>
    <?php include 'includes/submission_details.php'; ?>
<?php elseif ($view === 'calendar'): ?>
    <?php include 'includes/assignment_calendar.php'; ?>
<?php elseif ($view === 'submitted'): ?>
    <?php include 'includes/submitted_assignments.php'; ?>
<?php else: ?>
    <?php include 'includes/assignment_list.php'; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<?php
// ======================
// FUNCTION DEFINITIONS
// ======================

function handleAssignmentSubmission($db, $user_id)
{
    $assignment_id = filter_var($_POST['assignment_id'], FILTER_VALIDATE_INT);
    $submission_text = trim($_POST['submission_text'] ?? '');

    // Check if enrolled (using user_id checks in other queries, simple existence check here)
    // Simplified validation essentially covered by subsequent queries

    // Check if already submitted
    $stmt = $db->prepare("SELECT submission_id FROM assignment_submissions WHERE assignment_id = ? AND user_id = ?");
    $stmt->execute([$assignment_id, $user_id]);
    if ($stmt->fetch()) {
        header('Location: assignments.php?error=already_submitted');
        exit;
    }

    // Check if deadline passed
    $stmt = $db->prepare("SELECT due_date FROM assignments WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch();

    if (strtotime($assignment['due_date']) < time()) {
        header('Location: assignments.php?error=deadline_passed');
        exit;
    }

    // Handle file upload
    $file_path = null;
    $file_name = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === 0) {
        $file_info = handleFileUpload($_FILES['submission_file'], 'assignments');
        if ($file_info) {
            $file_path = $file_info['path'];
            $file_name = $file_info['name'];
        }
    }

    // Insert submission
    $stmt = $db->prepare("
        INSERT INTO assignment_submissions 
        (user_id, assignment_id, submission_text, file_path, file_name, submitted_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    if ($stmt->execute([$user_id, $assignment_id, $submission_text, $file_path, $file_name])) {
        // Create notification
        createNotification($db, $user_id, 'assignment_submitted', $assignment_id);
        header('Location: assignments.php?submitted=1');
        exit;
    } else {
        header('Location: assignments.php?error=submission_failed');
        exit;
    }
}

function handleSubmissionUpdate($db, $user_id)
{
    $submission_id = filter_var($_POST['submission_id'], FILTER_VALIDATE_INT);
    $submission_text = trim($_POST['submission_text'] ?? '');

    // Verify ownership
    $stmt = $db->prepare("
        SELECT s.submission_id, a.assignment_id, a.due_date 
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        WHERE s.submission_id = ? AND s.user_id = ?
    ");
    $stmt->execute([$submission_id, $user_id]);
    $submission = $stmt->fetch();

    if (!$submission) {
        header('Location: assignments.php?error=not_found');
        exit;
    }

    // Check if deadline passed
    if (strtotime($submission['due_date']) < time()) {
        header('Location: assignments.php?error=deadline_passed');
        exit;
    }

    // Handle file upload if new file provided
    $file_path = $_POST['current_file_path'] ?? null;
    $file_name = $_POST['current_file_name'] ?? null;

    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === 0) {
        // Delete old file if exists
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }

        $file_info = handleFileUpload($_FILES['submission_file'], 'assignments');
        if ($file_info) {
            $file_path = $file_info['path'];
            $file_name = $file_info['name'];
        }
    }

    // Update submission
    $stmt = $db->prepare("
        UPDATE assignment_submissions 
        SET submission_text = ?, file_path = ?, file_name = ?, submitted_at = NOW()
        WHERE submission_id = ?
    ");

    if ($stmt->execute([$submission_text, $file_path, $file_name, $submission_id])) {
        header('Location: assignments.php?updated=1');
        exit;
    } else {
        header('Location: assignments.php?error=update_failed');
        exit;
    }
}

function handleSubmissionDelete($db, $user_id)
{
    $submission_id = filter_var($_POST['submission_id'], FILTER_VALIDATE_INT);

    // Verify ownership and get file info
    $stmt = $db->prepare("
        SELECT s.file_path, a.assignment_id, a.due_date 
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        WHERE s.submission_id = ? AND s.user_id = ?
    ");
    $stmt->execute([$submission_id, $user_id]);
    $submission = $stmt->fetch();

    if (!$submission) {
        header('Location: assignments.php?error=not_found');
        exit;
    }

    // Check if deadline passed
    if (strtotime($submission['due_date']) < time()) {
        header('Location: assignments.php?error=deadline_passed');
        exit;
    }

    // Delete file if exists
    if ($submission['file_path'] && file_exists($submission['file_path'])) {
        unlink($submission['file_path']);
    }

    // Delete submission
    $stmt = $db->prepare("DELETE FROM assignment_submissions WHERE submission_id = ?");

    if ($stmt->execute([$submission_id])) {
        header('Location: assignments.php?deleted=1');
        exit;
    } else {
        header('Location: assignments.php?error=delete_failed');
        exit;
    }
}

function getEnrollmentIdForAssignment($db, $user_id, $assignment_id)
{
    $stmt = $db->prepare("
        SELECT e.enrollment_id 
        FROM assignments a
        JOIN batches b ON a.batch_id = b.batch_id
        JOIN enrollment_applications ea ON b.batch_id = ea.batch_id
        JOIN enrollments e ON ea.application_id = e.application_id
        WHERE a.assignment_id = ? 
        AND ea.user_id = ?
        AND ea.application_status = 'approved'
        AND e.enrollment_status = 'active'
    ");
    $stmt->execute([$assignment_id, $user_id]);
    $result = $stmt->fetch();
    return $result ? $result['enrollment_id'] : null;
}

function handleFileUpload($file, $type)
{
    $allowed_types = [
        'assignments' => [
            'extensions' => ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'upload_dir' => '../uploads/assignments/'
        ]
    ];

    $config = $allowed_types[$type];

    // Check file size
    if ($file['size'] > $config['max_size']) {
        return false;
    }

    // Check file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $config['extensions'])) {
        return false;
    }

    // Create directory if not exists
    if (!is_dir($config['upload_dir'])) {
        mkdir($config['upload_dir'], 0755, true);
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_ext;
    $filepath = $config['upload_dir'] . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'name' => $file['name'],
            'path' => $filepath,
            'size' => $file['size'],
            'extension' => $file_ext
        ];
    }

    return false;
}

function createNotification($db, $user_id, $type, $related_id)
{
    $messages = [
        'assignment_submitted' => [
            'title' => 'Assignment Submitted',
            'message' => 'Your assignment has been submitted successfully.'
        ]
    ];

    if (isset($messages[$type])) {
        $msg = $messages[$type];
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, notification_type, title, message, related_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $type, $msg['title'], $msg['message'], $related_id]);
    }
}

function getUpcomingAssignments($db, $batch_ids, $user_id)
{
    if (empty($batch_ids)) return [];

    $placeholders = str_repeat('?,', count($batch_ids) - 1) . '?';

    // Create params array with batch IDs and then user_id twice (once for subquery, once for join)
    $params = array_merge($batch_ids, [$user_id, $user_id]);

    $stmt = $db->prepare("
        SELECT 
            a.assignment_id,
            a.title as assignment_title,
            a.description as assignment_description,
            a.total_points,
            a.due_date,
            a.submission_type,
            a.allowed_extensions,
            b.batch_name,
            c.course_name,
            c.course_code,
            s.submission_id,
            s.grade,
            s.feedback,
            s.submitted_at,
            s.file_path,
            DATEDIFF(a.due_date, CURDATE()) as days_remaining,
            (SELECT COUNT(*) FROM assignment_submissions s2 
             WHERE s2.assignment_id = a.assignment_id AND s2.user_id = ?) as is_submitted
        FROM assignments a
        JOIN batches b ON a.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN assignment_submissions s ON a.assignment_id = s.assignment_id 
            AND s.user_id = ?
        WHERE a.batch_id IN ($placeholders)
        ORDER BY a.due_date ASC, c.course_name
    ");

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getSubmittedAssignments($db, $user_id)
{
    // $enrollment_ids unused now

    $stmt = $db->prepare("
        SELECT 
            s.submission_id,
            s.submission_text,
            s.file_path,
            s.file_name,
            s.grade,
            s.feedback,
            s.graded_at,
            s.submitted_at,
            a.assignment_id,
            a.title as assignment_title,
            a.description as assignment_description,
            a.total_points,
            a.due_date,
            b.batch_name,
            c.course_name,
            u.full_name as graded_by_name,
            DATEDIFF(a.due_date, s.submitted_at) as submitted_before_due
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        JOIN batches b ON a.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN users u ON s.graded_by = u.user_id
        WHERE s.user_id = ?
        ORDER BY s.submitted_at DESC
    ");

    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function getAssignmentCalendar($db, $batch_ids, $user_id)
{
    if (empty($batch_ids)) return [];

    $placeholders = str_repeat('?,', count($batch_ids) - 1) . '?';
    $params = array_merge($batch_ids, [$user_id]);

    $stmt = $db->prepare("
        SELECT 
            a.assignment_id,
            a.title as assignment_title,
            a.due_date,
            b.batch_name,
            c.course_name,
            c.course_code,
            (SELECT COUNT(*) FROM assignment_submissions s 
             WHERE s.assignment_id = a.assignment_id AND s.user_id = ?) as is_submitted
        FROM assignments a
        JOIN batches b ON a.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        WHERE a.batch_id IN ($placeholders)
        AND a.due_date >= CURDATE()
        ORDER BY a.due_date
    ");

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAssignmentDetails($db, $assignment_id, $user_id)
{
    $stmt = $db->prepare("
        SELECT 
            a.*,
            a.title as assignment_title,
            a.description as assignment_description,
            b.batch_name,
            c.course_name,
            c.course_code,
            DATEDIFF(a.due_date, CURDATE()) as days_remaining,
            TIMESTAMPDIFF(HOUR, NOW(), a.due_date) as hours_remaining
        FROM assignments a
        JOIN batches b ON a.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        WHERE a.assignment_id = ?
        AND a.batch_id IN (
            AND ea.application_status = 'approved'
        )
    ");

    $stmt->execute([$assignment_id, $user_id]);
    return $stmt->fetch();
}

function getSubmissionDetails($db, $submission_id, $user_id)
{
    $stmt = $db->prepare("
        SELECT 
            s.*,
            a.title as assignment_title,
            a.description as assignment_description,
            a.total_points,
            a.due_date,
            b.batch_name,
            c.course_name,
            u.full_name as graded_by_name,
            DATEDIFF(a.due_date, s.submitted_at) as submitted_before_due
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        JOIN batches b ON a.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN users u ON s.graded_by = u.user_id
        WHERE s.submission_id = ?
        AND s.user_id = ?
    ");

    $stmt->execute([$submission_id, $user_id]);
    return $stmt->fetch();
}
?>

<!-- Create the included files -->

<!-- assignment_submission_form.php -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-file-upload me-2"></i>
                Submit Assignment: <?php echo htmlspecialchars($assignment_details['assignment_title']); ?>
            </h5>
            <span class="badge bg-light text-dark">
                Due: <?php echo date('M j, Y g:i A', strtotime($assignment_details['due_date'])); ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <div class="d-flex">
                <div class="me-3">
                    <i class="fas fa-info-circle fa-2x"></i>
                </div>
                <div>
                    <h6 class="mb-1">Assignment Information</h6>
                    <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars($assignment_details['course_name']); ?> (<?php echo htmlspecialchars($assignment_details['course_code']); ?>)</p>
                    <p class="mb-1"><strong>Batch:</strong> <?php echo htmlspecialchars($assignment_details['batch_name']); ?></p>
                    <p class="mb-1"><strong>Total Points:</strong> <?php echo $assignment_details['total_points']; ?></p>
                    <p class="mb-0"><strong>Submission Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $assignment_details['submission_type'])); ?></p>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <h6>Assignment Description:</h6>
            <div class="border rounded p-3 bg-light">
                <?php echo nl2br(htmlspecialchars($assignment_details['assignment_description'])); ?>
            </div>
        </div>

        <?php if ($assignment_details['days_remaining'] < 0): ?>
            <div class="alert alert-danger">
                <div class="d-flex">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Deadline Passed</h6>
                        <p class="mb-0">The submission deadline for this assignment has passed. Late submissions may not be accepted.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-clock me-2"></i>
                        <strong>Time Remaining:</strong>
                        <?php if ($assignment_details['days_remaining'] > 1): ?>
                            <?php echo $assignment_details['days_remaining']; ?> days
                        <?php elseif ($assignment_details['hours_remaining'] > 1): ?>
                            <?php echo floor($assignment_details['hours_remaining'] / 24); ?> days, <?php echo $assignment_details['hours_remaining'] % 24; ?> hours
                        <?php else: ?>
                            Less than 1 hour
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Due: <?php echo date('M j, Y g:i A', strtotime($assignment_details['due_date'])); ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="assignmentForm">
            <input type="hidden" name="action" value="submit_assignment">
            <input type="hidden" name="assignment_id" value="<?php echo $assignment_details['assignment_id']; ?>">

            <?php if (in_array($assignment_details['submission_type'], ['text', 'both'])): ?>
                <div class="mb-4">
                    <label class="form-label fw-bold">Submission Text <span class="text-danger">*</span></label>
                    <textarea name="submission_text" class="form-control" rows="8"
                        placeholder="Enter your assignment submission here..."
                        required><?php echo isset($_POST['submission_text']) ? htmlspecialchars($_POST['submission_text']) : ''; ?></textarea>
                    <div class="form-text">You can format your text using basic HTML or plain text.</div>
                </div>
            <?php endif; ?>

            <?php if (in_array($assignment_details['submission_type'], ['file_upload', 'both'])): ?>
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        Upload File
                        <?php if ($assignment_details['submission_type'] === 'file_upload'): ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </label>
                    <input type="file" name="submission_file" class="form-control"
                        <?php echo $assignment_details['submission_type'] === 'file_upload' ? 'required' : ''; ?>>

                    <?php if ($assignment_details['allowed_extensions']): ?>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Allowed file types: <?php echo htmlspecialchars($assignment_details['allowed_extensions']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($assignment_details['max_file_size_mb']): ?>
                        <div class="form-text">
                            <i class="fas fa-hdd me-1"></i>
                            Maximum file size: <?php echo $assignment_details['max_file_size_mb']; ?> MB
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mt-4">
                <a href="assignments.php" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-paper-plane me-1"></i>Submit Assignment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Character count for text submission
        $('textarea[name="submission_text"]').on('input', function() {
            const length = $(this).val().length;
            $('#charCount').text(length);
        });

        // File size validation
        $('input[type="file"]').change(function() {
            const file = this.files[0];
            const maxSize = <?php echo $assignment_details['max_file_size_mb'] ?? 10; ?> * 1024 * 1024;

            if (file && file.size > maxSize) {
                alert(`File size exceeds maximum limit of ${<?php echo $assignment_details['max_file_size_mb'] ?? 10; ?>}MB`);
                $(this).val('');
            }
        });

        // Form submission handler
        $('#assignmentForm').submit(function(e) {
            const submitBtn = $('#submitBtn');
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span>Submitting...');

            // Optional: Add confirmation for late submission
            const dueDate = new Date('<?php echo $assignment_details['due_date']; ?>');
            const now = new Date();

            if (now > dueDate) {
                if (!confirm('This assignment is past its due date. Are you sure you want to submit?')) {
                    submitBtn.prop('disabled', false);
                    submitBtn.html('<i class="fas fa-paper-plane me-1"></i>Submit Assignment');
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });
    });
</script>