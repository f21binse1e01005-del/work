<?php
/**
 * Database Backup System
 */

require_once '../config/security.php';
require_once '../config/middleware.php';

// Initialize security
Security::init();
Middleware::admin_access();

class DatabaseBackup {
    private $db;
    private $backupDir;
    
    public function __construct() {
        require_once '../config/database.php';
        $this->db = (new Database())->getConnection();
        $this->backupDir = __DIR__ . '/../backups/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function createBackup($includeData = true) {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "skills_way_backup_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;
        
        try {
            $sql = $this->generateBackupSQL($includeData);
            file_put_contents($filepath, $sql);
            
            // Compress backup
            $this->compressBackup($filepath);
            
            return [
                'success' => true,
                'filename' => $filename . '.gz',
                'size' => filesize($filepath . '.gz'),
                'path' => $filepath . '.gz'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function generateBackupSQL($includeData) {
        $sql = "-- Skills Way Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Get all tables
        $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $sql .= $this->getTableStructure($table);
            
            if ($includeData) {
                $sql .= $this->getTableData($table);
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }
    
    private function getTableStructure($table) {
        $sql = "\n-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $result = $this->db->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= $result['Create Table'] . ";\n\n";
        
        return $sql;
    }
    
    private function getTableData($table) {
        $sql = "-- Data for table: $table\n";
        
        $stmt = $this->db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return $sql . "-- No data\n\n";
        }
        
        $columns = array_keys($rows[0]);
        $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
        
        $values = [];
        foreach ($rows as $row) {
            $rowValues = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $rowValues[] = 'NULL';
                } else {
                    $rowValues[] = "'" . addslashes($value) . "'";
                }
            }
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }
        
        $sql .= implode(",\n", $values) . ";\n\n";
        return $sql;
    }
    
    private function compressBackup($filepath) {
        $gz = gzopen($filepath . '.gz', 'w9');
        gzwrite($gz, file_get_contents($filepath));
        gzclose($gz);
        unlink($filepath); // Remove uncompressed file
    }
    
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . '*.gz');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'path' => $file
            ];
        }
        
        return array_reverse($backups); // Latest first
    }
    
    public function downloadBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    public function deleteBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
}

// Handle requests
$backup = new DatabaseBackup();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token validation failed';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $includeData = isset($_POST['include_data']);
                $result = $backup->createBackup($includeData);
                
                if ($result['success']) {
                    $message = 'Backup created successfully: ' . $result['filename'];
                    $messageType = 'success';
                } else {
                    $message = 'Backup failed: ' . $result['message'];
                    $messageType = 'danger';
                }
                break;
                
            case 'download':
                $filename = $_POST['filename'] ?? '';
                $backup->downloadBackup($filename);
                break;
                
            case 'delete':
                $filename = $_POST['filename'] ?? '';
                if ($backup->deleteBackup($filename)) {
                    $message = 'Backup deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete backup';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

$backups = $backup->listBackups();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Skills Way</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1><i class="fas fa-database me-2"></i>Database Backup System</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Create New Backup</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="include_data" id="include_data" checked>
                                <label class="form-check-label" for="include_data">
                                    Include table data
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Existing Backups</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <p class="text-muted">No backups found</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Date</th>
                                            <th>Size</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                            <td><?php echo $backup['date']; ?></td>
                                            <td><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="download">
                                                    <input type="hidden" name="filename" value="<?php echo $backup['filename']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this backup?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="filename" value="<?php echo $backup['filename']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>