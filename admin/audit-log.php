<?php
session_start();
require_once 'includes/header.php';

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if (!$type || !$id) {
    header('Location: dashboard.php');
    exit;
}

// Get audit logs based on type
$logs = [];
if ($type === 'application') {
    $stmt = $db->prepare("SELECT ual.*, u.full_name FROM user_activity_logs ual LEFT JOIN users u ON ual.user_id = u.user_id WHERE JSON_EXTRACT(ual.activity_details, '$.application_id') = ? ORDER BY ual.performed_at DESC");
    $stmt->execute([$id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Audit Log - <?php echo ucfirst($type); ?> #<?php echo htmlspecialchars($id); ?></h1>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Activity History</h5>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No audit logs found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Activity</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('M d, Y g:i A', strtotime($log['performed_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($log['activity_type']); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td>
                                        <?php 
                                        $details = json_decode($log['activity_details'], true);
                                        if ($details) {
                                            foreach ($details as $key => $value) {
                                                echo "<small><strong>$key:</strong> " . htmlspecialchars($value) . "<br></small>";
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>