<?php
/**
 * Batch View Page
 * File: admin/batch-view.php
 */

require_once 'includes/header.php';

$batch_id = $_GET['id'] ?? 0;

// Get batch details
$batchQuery = "
    SELECT b.*, c.course_name, c.course_code, u.full_name as teacher_name
    FROM batches b
    JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN users u ON b.teacher_id = u.user_id
    WHERE b.batch_id = ?
";
$stmt = $db->prepare($batchQuery);
$stmt->execute([$batch_id]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    header('Location: batches.php');
    exit;
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-eye me-2"></i>Batch Details</h1>
        <div>
            <a href="batch-edit.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>Edit Batch
            </a>
            <a href="batches.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Batches
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Batch Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Batch Code</label>
                            <p class="fw-bold"><?php echo $batch['batch_code']; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Batch Name</label>
                            <p><?php echo $batch['batch_name']; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Course</label>
                            <p><?php echo $batch['course_name']; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Teacher</label>
                            <p><?php echo $batch['teacher_name'] ?: 'Not Assigned'; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Start Date</label>
                            <p><?php echo date('F d, Y', strtotime($batch['start_date'])); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">End Date</label>
                            <p><?php echo date('F d, Y', strtotime($batch['end_date'])); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Status</label>
                            <p>
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
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Classroom</label>
                            <p><?php echo $batch['classroom'] ?: 'Not Assigned'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Stats</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Duration:</span>
                        <strong>
                            <?php 
                            $start = new DateTime($batch['start_date']);
                            $end = new DateTime($batch['end_date']);
                            $diff = $start->diff($end);
                            echo $diff->days . ' days';
                            ?>
                        </strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Enrolled Students:</span>
                        <strong>0</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>