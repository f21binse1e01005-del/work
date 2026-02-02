<?php
/**
 * System Logs - Error and System Event Tracking
 * File: admin/system-logs.php
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

// Read PHP error log
$errorLogPath = ini_get('error_log') ?: '/var/log/php_errors.log';
$customLogPath = '../logs/database.log';

$phpErrors = [];
$systemLogs = [];

// Read PHP error log (last 100 lines)
if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
    $phpErrors = array_slice(file($errorLogPath), -100);
    $phpErrors = array_reverse($phpErrors);
}

// Read custom system logs
if (file_exists($customLogPath) && is_readable($customLogPath)) {
    $systemLogs = array_slice(file($customLogPath), -100);
    $systemLogs = array_reverse($systemLogs);
}

// System information
$systemInfo = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'disk_free_space' => disk_free_space('.') ? round(disk_free_space('.') / 1024 / 1024 / 1024, 2) . ' GB' : 'Unknown'
];

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-server me-2"></i>System Logs</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-outline-danger btn-sm" onclick="clearLogs()" title="Clear Logs">
                <i class="fas fa-trash me-1"></i>Clear Logs
            </button>
        </div>
    </div>

    <!-- System Information -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-1"></i>System Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($systemInfo as $key => $value): ?>
                        <div class="col-md-3 mb-2">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($value); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Tabs -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#php-errors" role="tab">
                        <i class="fas fa-exclamation-triangle me-1"></i>PHP Errors (<?php echo count($phpErrors); ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#system-logs" role="tab">
                        <i class="fas fa-list me-1"></i>System Logs (<?php echo count($systemLogs); ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#database-status" role="tab">
                        <i class="fas fa-database me-1"></i>Database Status
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- PHP Errors Tab -->
                <div class="tab-pane fade show active" id="php-errors" role="tabpanel">
                    <?php if (empty($phpErrors)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">No PHP errors found!</p>
                        </div>
                    <?php else: ?>
                        <div class="log-container" style="max-height: 500px; overflow-y: auto;">
                            <?php foreach ($phpErrors as $error): ?>
                                <?php if (trim($error)): ?>
                                <div class="log-entry mb-2 p-2 border-start border-danger border-3 bg-light">
                                    <code class="text-danger small"><?php echo htmlspecialchars($error); ?></code>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- System Logs Tab -->
                <div class="tab-pane fade" id="system-logs" role="tabpanel">
                    <?php if (empty($systemLogs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No system logs available.</p>
                        </div>
                    <?php else: ?>
                        <div class="log-container" style="max-height: 500px; overflow-y: auto;">
                            <?php foreach ($systemLogs as $log): ?>
                                <?php if (trim($log)): ?>
                                <div class="log-entry mb-2 p-2 border-start border-info border-3 bg-light">
                                    <code class="text-info small"><?php echo htmlspecialchars($log); ?></code>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Database Status Tab -->
                <div class="tab-pane fade" id="database-status" role="tabpanel">
                    <?php
                    try {
                        $db = (new Database())->getConnection();
                        if ($db) {
                            // Get database info
                            $dbInfo = $db->query("SELECT VERSION() as version")->fetch();
                            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                            
                            // Get table sizes
                            $tableSizes = $db->query("
                                SELECT table_name, 
                                       ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                                FROM information_schema.TABLES 
                                WHERE table_schema = DATABASE()
                                ORDER BY size_mb DESC
                            ")->fetchAll();
                    ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-database me-1"></i>Database Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Version:</strong></td><td><?php echo htmlspecialchars($dbInfo['version']); ?></td></tr>
                                    <tr><td><strong>Tables:</strong></td><td><?php echo count($tables); ?></td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-success">Connected</span></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-table me-1"></i>Table Sizes</h6>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-sm">
                                        <?php foreach ($tableSizes as $table): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($table['table_name']); ?></td>
                                            <td class="text-end"><?php echo $table['size_mb']; ?> MB</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php
                        } else {
                            echo '<div class="alert alert-danger">Database connection failed!</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function clearLogs() {
    if (confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
        fetch('clear-logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to clear logs: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

// Auto-refresh every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php require_once 'includes/footer.php'; ?>