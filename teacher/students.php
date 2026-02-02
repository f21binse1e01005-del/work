<?php
// Initialize security and middleware
require_once '../config/security.php';
require_once '../config/middleware.php';
require_once '../config/notifications.php';

// Initialize security
Security::init();

// Require teacher access
Middleware::teacher_access();

// Handle notification sending
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token validation failed';
        $messageType = 'danger';
    } else {
        $notificationService = new NotificationService();
        
        if ($_POST['action'] === 'send_welcome') {
            $userId = Middleware::sanitizeInput($_POST['user_id'], 'int');
            $result = $notificationService->sendWelcomeMessage($userId);
            
            if (isset($result['email']) && $result['email']['success']) {
                $message = 'Welcome message sent successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to send welcome message';
                $messageType = 'danger';
            }
        }
    }
}

$pageTitle = 'Students';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$batch_id = Middleware::sanitizeInput($_GET['batch_id'] ?? null, 'int');

// Get classes for dropdown
$classes = $db->query("SELECT b.batch_id, b.batch_name, c.course_name FROM batches b JOIN courses c ON b.course_id = c.course_id ORDER BY b.start_date DESC")->fetchAll();

// Get class info and students if batch selected
$class_info = null;
$students = [];
if ($batch_id) {
    $stmt = $db->prepare("SELECT b.batch_name, c.course_name, b.status FROM batches b JOIN courses c ON b.course_id = c.course_id WHERE b.batch_id = ?");
    $stmt->execute([$batch_id]);
    $class_info = $stmt->fetch();
    
    $stmt = $db->prepare("SELECT u.user_id, u.full_name, u.email, u.phone, ea.application_date, ea.payment_status 
        FROM users u JOIN enrollment_applications ea ON u.user_id = ea.user_id 
        WHERE ea.batch_id = ? AND ea.application_status = 'approved' ORDER BY u.full_name");
    $stmt->execute([$batch_id]);
    $students = $stmt->fetchAll();
}
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-users me-2"></i>Students</h1>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <label class="form-label">Select Class:</label>
                <select class="form-select" onchange="location.href='students.php?batch_id='+this.value">
                    <option value="">Choose a class...</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['batch_id']; ?>" <?php echo $batch_id == $class['batch_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['batch_name']); ?> - <?php echo htmlspecialchars($class['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($class_info): ?>
            <div class="col-md-6">
                <div class="d-flex">
                    <div class="me-4">
                        <small class="text-muted">Students</small>
                        <div class="h5"><?php echo count($students); ?></div>
                    </div>
                    <div>
                        <small class="text-muted">Status</small>
                        <div class="h5"><?php echo ucfirst($class_info['status'] ?? 'Active'); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($batch_id): ?>
<div class="card">
    <div class="card-header">
        <h5>Students in <?php echo htmlspecialchars($class_info['batch_name'] ?? 'Selected Class'); ?></h5>
    </div>
    <div class="card-body">
        <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-graduate fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No students enrolled</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Enrollment Date</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($student['application_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $student['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($student['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="send_welcome">
                                    <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Send Welcome Message">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-chalkboard fa-4x text-muted mb-3"></i>
        <h5 class="text-muted">Select a Class</h5>
        <p class="text-muted">Choose a class to view enrolled students</p>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>