<?php
$pageTitle = 'Classes';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$message = '';

// Handle class creation
if ($_POST['action'] ?? '' === 'create') {
    try {
        $stmt = $db->prepare("INSERT INTO batches (course_id, batch_name, start_date, max_students, status) VALUES (?, ?, ?, ?, 'upcoming')");
        $stmt->execute([$_POST['course_id'], $_POST['batch_name'], $_POST['start_date'], $_POST['max_students']]);
        $message = '<div class="alert alert-success">Class created successfully!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error creating class</div>';
    }
}

// Get courses and classes
$courses = $db->query("SELECT course_id, course_name FROM courses WHERE is_active = 1")->fetchAll();
$classes = $db->query("SELECT b.*, c.course_name, 
    (SELECT COUNT(*) FROM enrollment_applications WHERE batch_id = b.batch_id AND application_status = 'approved') as enrolled
    FROM batches b JOIN courses c ON b.course_id = c.course_id ORDER BY b.start_date DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-chalkboard me-2"></i>Classes</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i>Create Class
    </button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($classes)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chalkboard fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No classes created yet</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Course</th>
                            <th>Students</th>
                            <th>Start Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['batch_name']); ?></td>
                            <td><?php echo htmlspecialchars($class['course_name']); ?></td>
                            <td><span class="badge bg-primary"><?php echo $class['enrolled']; ?></span> / <?php echo $class['max_students']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($class['start_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $class['status'] === 'ongoing' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($class['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="students.php?batch_id=<?php echo $class['batch_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-users"></i>
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

<!-- Create Modal -->
<div class="modal fade" id="createModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="batch_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Max Students</label>
                                <input type="number" name="max_students" class="form-control" value="30" min="1" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>