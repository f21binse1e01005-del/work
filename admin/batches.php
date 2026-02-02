<?php
/**
 * Admin Batches Management
 * File: admin/batches.php
 */

// Handle batch creation/editing BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    
    $db = (new Database())->getConnection();
    
    $course_id = $_POST['course_id'];
    $batch_name = $_POST['batch_name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $teacher_id = $_POST['teacher_id'] ?: null;
    
    $batch_code = strtoupper(substr($_POST['course_code'], 0, 3)) . '-' . date('MY', strtotime($start_date));
    
    $sql = "INSERT INTO batches (course_id, batch_code, batch_name, start_date, end_date, teacher_id) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$course_id, $batch_code, $batch_name, $start_date, $end_date, $teacher_id]);
    
    header('Location: batches.php?success=1');
    exit;
}

require_once 'includes/header.php';

// Get batches with course and teacher info
$batchesQuery = "
    SELECT b.*, c.course_name, c.course_code, u.full_name as teacher_name,
           COUNT(ea.application_id) as enrolled_count
    FROM batches b
    JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN users u ON b.teacher_id = u.user_id
    LEFT JOIN enrollment_applications ea ON b.batch_id = ea.batch_id 
        AND ea.application_status = 'approved'
    GROUP BY b.batch_id
    ORDER BY b.start_date DESC
";
$batches = $db->query($batchesQuery);

// Get courses for dropdown
$coursesQuery = "SELECT * FROM courses WHERE is_active = 1 ORDER BY course_name";
$courses = $db->query($coursesQuery);

// Get teachers for dropdown
$teachersQuery = "SELECT user_id, full_name FROM users WHERE user_type = 'teacher' AND account_status = 'active'";
$teachers = $db->query($teachersQuery);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-calendar-alt me-2"></i>Batch Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
            <i class="fas fa-plus me-1"></i>Add New Batch
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Batch created successfully!</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>Batch Code</th>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Duration</th>
                            <th>Enrolled</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($batch = $batches->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><strong><?php echo $batch['batch_code']; ?></strong></td>
                                <td><?php echo $batch['course_name']; ?></td>
                                <td><?php echo $batch['teacher_name'] ?: 'Not Assigned'; ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($batch['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($batch['end_date'])); ?>
                                </td>
                                <td><?php echo $batch['enrolled_count']; ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'upcoming' => 'bg-info',
                                        'ongoing' => 'bg-success',
                                        'completed' => 'bg-secondary',
                                        'cancelled' => 'bg-danger'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $statusClass[$batch['status']]; ?>">
                                        <?php echo ucfirst($batch['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="batch-view.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="batch-edit.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">Select Course</option>
                                <?php 
                                $coursesForModal = $db->query($coursesQuery);
                                while($course = $coursesForModal->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $course['course_id']; ?>" data-code="<?php echo $course['course_code']; ?>">
                                        <?php echo $course['course_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <input type="hidden" name="course_code" id="course_code">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Name</label>
                            <input type="text" name="batch_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control date-picker" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control date-picker" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teacher</label>
                            <select name="teacher_id" class="form-select">
                                <option value="">Select Teacher</option>
                                <?php 
                                $teachersForModal = $db->query($teachersQuery);
                                while($teacher = $teachersForModal->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $teacher['user_id']; ?>">
                                        <?php echo $teacher['full_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update course code when course is selected
document.querySelector('select[name="course_id"]').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    document.getElementById('course_code').value = selectedOption.dataset.code || '';
});
</script>

<?php require_once 'includes/footer.php'; ?>