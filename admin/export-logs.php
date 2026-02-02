<?php
/**
 * Export Logs
 * File: admin/export-logs.php
 */

if (isset($_GET['export']) && $_GET['export'] == '1') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr>";
    echo "</table>";
    exit;
}

echo "No logs to export.";
?>