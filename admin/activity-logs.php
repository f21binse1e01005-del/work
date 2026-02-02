<?php
/**
 * Activity Logs - User Login Trails
 * File: admin/activity-logs.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();

// Filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'all';
$userType = $_GET['user_type'] ?? 'all';

// Get login logs
$query = "SELECT ll.*, u.full_name, u.user_type 
          FROM login_logs ll 
          LEFT JOIN users u ON ll.user_id = u.user_id 
          WHERE DATE(ll.login_time) BETWEEN ? AND ?";

$params = [$dateFrom, $dateTo];

if ($status !== 'all') {
    $query .= " AND ll.login_status = ?";
    $params[] = $status;
}

if ($userType !== 'all') {
    $query .= " AND u.user_type = ?";
    $params[] = $userType;
}

$query .= " ORDER BY ll.login_time DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total_attempts,
    SUM(CASE WHEN login_status = 'success' THEN 1 ELSE 0 END) as successful_logins,
    SUM(CASE WHEN login_status != 'success' THEN 1 ELSE 0 END) as failed_attempts,
    COUNT(DISTINCT ip_address) as unique_ips
    FROM login_logs 
    WHERE DATE(login_time) BETWEEN ? AND ?";

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute([$dateFrom, $dateTo]);
$stats = $statsStmt->fetch() ?: [
    'total_attempts' => 0,
    'successful_logins' => 0,
    'failed_attempts' => 0,
    'unique_ips' => 0
];

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-history me-2"></i>Activity Logs</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-outline-primary btn-sm" onclick="exportLogs()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-primary"><?php echo number_format($stats['total_attempts']); ?></h4>
                    <small class="text-muted">Total Attempts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-success"><?php echo number_format($stats['successful_logins']); ?></h4>
                    <small class="text-muted">Successful Logins</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-danger"><?php echo number_format($stats['failed_attempts']); ?></h4>
                    <small class="text-muted">Failed Attempts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-info"><?php echo number_format($stats['unique_ips']); ?></h4>
                    <small class="text-muted">Unique IPs</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="failed_password" <?php echo $status === 'failed_password' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">User Type</label>
                    <select name="user_type" class="form-select">
                        <option value="all" <?php echo $userType === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="admin" <?php echo $userType === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="teacher" <?php echo $userType === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="student" <?php echo $userType === 'student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Login Activity (<?php echo count($logs); ?> records)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small><?php echo date('M d, Y H:i:s', strtotime($log['login_time'])); ?></small>
                            </td>
                            <td>
                                <div>
                                    <?php if ($log['full_name']): ?>
                                        <strong><?php echo htmlspecialchars($log['full_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo htmlspecialchars($log['username']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $log['login_status'] === 'success' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['login_status'])); ?>
                                </span>
                                <?php if ($log['user_type']): ?>
                                    <br><small class="text-muted"><?php echo ucfirst($log['user_type']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                            </td>
                            <td>
                                <small class="text-muted" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                    <?php 
                                    $agent = $log['user_agent'];
                                    if (strpos($agent, 'Chrome') !== false) echo '<i class="fab fa-chrome"></i> Chrome';
                                    elseif (strpos($agent, 'Firefox') !== false) echo '<i class="fab fa-firefox"></i> Firefox';
                                    elseif (strpos($agent, 'Safari') !== false) echo '<i class="fab fa-safari"></i> Safari';
                                    else echo '<i class="fas fa-globe"></i> Other';
                                    ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo htmlspecialchars($log['failure_reason'] ?? '-'); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'export-logs.php?' + params.toString();
}
</script>

<?php require_once 'includes/footer.php'; ?>