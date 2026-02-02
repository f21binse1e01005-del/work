<?php
/**
 * User Activity Logs - Detailed Action Tracking
 * File: admin/user-activity.php
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();

// Filters
$userId = $_GET['user_id'] ?? '';
$activityType = $_GET['activity_type'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Get activities
$query = "SELECT ual.*, u.full_name, u.username, u.user_type 
          FROM user_activity_logs ual 
          JOIN users u ON ual.user_id = u.user_id 
          WHERE DATE(ual.performed_at) BETWEEN ? AND ?";

$params = [$dateFrom, $dateTo];

if ($userId) {
    $query .= " AND ual.user_id = ?";
    $params[] = $userId;
}

if ($activityType !== 'all') {
    $query .= " AND ual.activity_type = ?";
    $params[] = $activityType;
}

$query .= " ORDER BY ual.performed_at DESC LIMIT 500";

$stmt = $db->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get activity types for filter
$activityTypes = $db->query("SELECT DISTINCT activity_type FROM user_activity_logs ORDER BY activity_type")->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users = $db->query("SELECT user_id, full_name, username FROM users ORDER BY full_name")->fetchAll();

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tasks me-2"></i>User Activity Logs</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $userId == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Activity Type</label>
                    <select name="activity_type" class="form-select">
                        <option value="all">All Types</option>
                        <?php foreach ($activityTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $activityType === $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Activity Timeline (<?php echo count($activities); ?> records)</h6>
        </div>
        <div class="card-body">
            <?php if (empty($activities)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No activities found for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($activities as $activity): 
                        $details = json_decode($activity['activity_details'], true);
                        $iconMap = [
                            'login' => 'sign-in-alt',
                            'logout' => 'sign-out-alt',
                            'registration' => 'user-plus',
                            'profile_update' => 'user-edit',
                            'course_enrollment' => 'book',
                            'payment' => 'credit-card',
                            'document_upload' => 'file-upload'
                        ];
                        $icon = $iconMap[$activity['activity_type']] ?? 'circle';
                        $colorMap = [
                            'login' => 'success',
                            'logout' => 'secondary',
                            'registration' => 'primary',
                            'profile_update' => 'info',
                            'course_enrollment' => 'warning',
                            'payment' => 'success',
                            'document_upload' => 'info'
                        ];
                        $color = $colorMap[$activity['activity_type']] ?? 'secondary';
                    ?>
                    <div class="timeline-item mb-4">
                        <div class="row">
                            <div class="col-auto">
                                <div class="timeline-badge bg-<?php echo $color; ?> text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($activity['full_name']); ?>
                                                    <span class="badge bg-<?php echo $color; ?> ms-2">
                                                        <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                                    </span>
                                                </h6>
                                                <small class="text-muted">
                                                    @<?php echo htmlspecialchars($activity['username']); ?> 
                                                    • <?php echo ucfirst($activity['user_type']); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y H:i:s', strtotime($activity['performed_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($details): ?>
                                            <div class="small text-muted">
                                                <?php foreach ($details as $key => $value): ?>
                                                    <span class="me-3">
                                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong> 
                                                        <?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2 small text-muted">
                                            <i class="fas fa-network-wired me-1"></i><?php echo htmlspecialchars($activity['ip_address']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.timeline {
    position: relative;
}
.timeline-item {
    position: relative;
}
.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 19px;
    top: 50px;
    width: 2px;
    height: calc(100% - 10px);
    background: #dee2e6;
}
</style>

<?php require_once 'includes/footer.php'; ?>