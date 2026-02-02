<?php
session_start();
$pageTitle = 'Calendar';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();

// Handle form submission
if ($_POST['action'] ?? '' === 'create') {
    $stmt = $db->prepare("INSERT INTO calendar_events (batch_id, title, description, event_date, event_time, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['batch_id'], $_POST['title'], $_POST['description'], $_POST['event_date'], $_POST['event_time'], $_SESSION['user_id']]);
    header('Location: calendar.php');
    exit;
}

// Get classes for dropdown
$classes = $db->query("SELECT batch_id, batch_name FROM batches ORDER BY start_date DESC")->fetchAll();

// Get upcoming events
$events = $db->query("SELECT e.*, b.batch_name 
    FROM calendar_events e 
    LEFT JOIN batches b ON e.batch_id = b.batch_id 
    WHERE e.event_date >= CURDATE() 
    ORDER BY e.event_date, e.event_time")->fetchAll();
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-calendar me-2"></i>Calendar</h1>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5>Add New Event</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Class</label>
                    <select name="batch_id" class="form-select" required>
                        <option value="">Select class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['batch_id']; ?>"><?php echo htmlspecialchars($class['batch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Event Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Time</label>
                    <input type="time" name="event_time" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Event</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Upcoming Events</h5>
    </div>
    <div class="card-body">
        <?php if (empty($events)): ?>
            <div class="text-center py-4">
                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">No upcoming events</p>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <h6><?php echo htmlspecialchars($event['title']); ?></h6>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($event['batch_name'] ?? 'General'); ?></span>
                    </div>
                    <p class="mb-1 text-muted">
                        <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                        <i class="fas fa-clock ms-3 me-1"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                    </p>
                    <?php if ($event['description']): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>