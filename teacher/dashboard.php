<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();

// Get stats
$stats = ['classes' => 0, 'students' => 0, 'active' => 0];
try {
    $result = $db->query("SELECT 
        (SELECT COUNT(*) FROM batches) as classes,
        (SELECT COUNT(*) FROM enrollment_applications WHERE application_status = 'approved') as students,
        (SELECT COUNT(*) FROM batches WHERE status = 'ongoing') as active");
    $stats = $result->fetch() ?: $stats;
} catch (Exception $e) {}
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-chalkboard fa-3x text-primary mb-3"></i>
                <h3><?php echo $stats['classes']; ?></h3>
                <p class="text-muted">Total Classes</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x text-success mb-3"></i>
                <h3><?php echo $stats['students']; ?></h3>
                <p class="text-muted">Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-play-circle fa-3x text-warning mb-3"></i>
                <h3><?php echo $stats['active']; ?></h3>
                <p class="text-muted">Active Classes</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <a href="classes.php" class="card text-decoration-none">
            <div class="card-body text-center">
                <i class="fas fa-chalkboard fa-3x text-primary mb-3"></i>
                <h5 class="text-dark">Manage Classes</h5>
                <p class="text-muted">Create and manage your classes</p>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="students.php" class="card text-decoration-none">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x text-success mb-3"></i>
                <h5 class="text-dark">View Students</h5>
                <p class="text-muted">View enrolled students per class</p>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-bullhorn me-2"></i>Announcements</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Welcome to the Teacher Portal. Use the navigation to manage your classes and view students.
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>