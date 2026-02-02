<?php
/**
 * Batch Edit Page
 * File: admin/batch-edit.php
 */

// Handle batch update BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    
    $db = (new Database())->getConnection();
    
    $batch_id = $_POST['batch_id'];
    $batch_name = $_POST['batch_name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $teacher_id = $_POST['teacher_id'] ?: null;
    $status = $_POST['status'];
    $classroom = $_POST['classroom'];
    
    $sql = "UPDATE batches SET batch_name = ?, start_date = ?, end_date = ?, teacher_id = ?, status = ?, classroom = ? WHERE batch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$batch_name, $start_date, $end_date, $teacher_id, $status, $classroom, $batch_id]);
    
    header('Location: batch-view.php?id=' . $batch_id . '&updated=1');
    exit;
}

require_once 'includes/header.php';

$batch_id = $_GET['id'] ?? 0;

// Get batch details
$batchQuery = "
    SELECT b.*, c.course_name, c.course_code
    FROM batches b
    JOIN courses c ON b.course_id = c.course_id
    WHERE b.batch_id = ?
";
$stmt = $db->prepare($batchQuery);
$stmt->execute([$batch_id]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    header('Location: batches.php');
    exit;
}

// Get teachers for dropdown
$teachersQuery = "SELECT user_id, full_name FROM users WHERE user_type = 'teacher' AND account_status = 'active'";
$teachers = $db->query($teachersQuery);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit me-2"></i>Edit Batch</h1>
        <a href="batch-view.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to View
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Batch: <?php echo $batch['batch_code']; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="batch_id" value="<?php echo $batch['batch_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course</label>
                                <input type="text" class="form-control" value="<?php echo $batch['course_name']; ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Batch Code</label>
                                <input type="text" class="form-control" value="<?php echo $batch['batch_code']; ?>" readonly>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Batch Name</label>
                                <input type="text" name="batch_name" class="form-control" value="<?php echo $batch['batch_name']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $batch['start_date']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $batch['end_date']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher</label>
                                <select name="teacher_id" class="form-select">
                                    <option value="">Select Teacher</option>
                                    <?php while($teacher = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $teacher['user_id']; ?>" 
                                                <?php echo ($batch['teacher_id'] == $teacher['user_id']) ? 'selected' : ''; ?>>
                                            <?php echo $teacher['full_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="upcoming" <?php echo ($batch['status'] == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="ongoing" <?php echo ($batch['status'] == 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="completed" <?php echo ($batch['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($batch['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Classroom</label>
                                <input type="text" name="classroom" class="form-control" value="<?php echo $batch['classroom']; ?>" placeholder="e.g., Room 101, Lab A">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <a href="batch-view.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Batch</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>