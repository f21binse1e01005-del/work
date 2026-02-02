<?php
session_start();
$pageTitle = 'Announcements';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();

// Handle form submission
if ($_POST['action'] ?? '' === 'create') {
    $stmt = $db->prepare("INSERT INTO announcements (batch_id, title, content, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['batch_id'], $_POST['title'], $_POST['content'], $_SESSION['user_id']]);
    header('Location: announcements.php');
    exit;
}

// Get classes for dropdown
$classes = $db->query("SELECT batch_id, batch_name FROM batches ORDER BY start_date DESC")->fetchAll();

// Get announcements
$announcements = $db->query("SELECT a.*, b.batch_name, u.full_name as teacher_name 
    FROM announcements a 
    JOIN batches b ON a.batch_id = b.batch_id 
    JOIN users u ON a.created_by = u.user_id 
    ORDER BY a.created_at DESC")->fetchAll();
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-bullhorn me-2"></i>Announcements</h1>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5>Post New Announcement</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="mb-3">
                <label class="form-label">Class</label>
                <select name="batch_id" class="form-select" required>
                    <option value="">Select class...</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['batch_id']; ?>"><?php echo htmlspecialchars($class['batch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Content</label>
                <textarea name="content" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Post Announcement</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Recent Announcements</h5>
    </div>
    <div class="card-body">
        <?php if (empty($announcements)): ?>
            <div class="text-center py-4">
                <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                <p class="text-muted">No announcements yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <h6><?php echo htmlspecialchars($announcement['title']); ?></h6>
                        <small class="text-muted"><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></small>
                    </div>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    <small class="text-muted">Class: <?php echo htmlspecialchars($announcement['batch_name']); ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>