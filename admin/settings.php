<?php
/**
 * Admin Settings
 * File: admin/settings.php
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = (new Database())->getConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $db->prepare("UPDATE institute_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    $success = "Settings updated successfully!";
}

// Get current settings
$settingsQuery = "SELECT * FROM institute_settings ORDER BY category, setting_key";
$settings = $db->query($settingsQuery);

// Group settings by category
$groupedSettings = [];
while ($setting = $settings->fetch(PDO::FETCH_ASSOC)) {
    $groupedSettings[$setting['category']][] = $setting;
}

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-cog me-2"></i>System Settings</h1>
        <button type="submit" form="settingsForm" class="btn btn-primary">
            <i class="fas fa-save me-1"></i>Save Changes
        </button>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form id="settingsForm" method="POST">
        <div class="row">
            <?php foreach ($groupedSettings as $category => $categorySettings): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 text-capitalize">
                                <i class="fas fa-<?php echo $category === 'general' ? 'info-circle' : ($category === 'contact' ? 'phone' : ($category === 'security' ? 'shield-alt' : 'cog')); ?> me-2"></i>
                                <?php echo ucwords(str_replace('_', ' ', $category)); ?> Settings
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($categorySettings as $setting): ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                    </label>
                                    
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                   value="true"
                                                   <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                <?php echo $setting['description']; ?>
                                            </label>
                                        </div>
                                    <?php elseif ($setting['setting_type'] === 'integer'): ?>
                                        <input type="number" 
                                               class="form-control" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php else: ?>
                                        <input type="text" 
                                               class="form-control" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                    
                                    <?php if ($setting['description']): ?>
                                        <small class="form-text text-muted"><?php echo $setting['description']; ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>

    <!-- System Information -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0"><i class="fas fa-server me-2"></i>System Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Database:</strong></td>
                            <td><?php echo $db->query("SELECT VERSION() as version")->fetch()['version']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Server Software:</strong></td>
                            <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0"><i class="fas fa-database me-2"></i>Database Statistics</h6>
                </div>
                <div class="card-body">
                    <?php
                    $dbStats = $db->query("
                        SELECT 
                            (SELECT COUNT(*) FROM users) as total_users,
                            (SELECT COUNT(*) FROM courses) as total_courses,
                            (SELECT COUNT(*) FROM batches) as total_batches,
                            (SELECT COUNT(*) FROM enrollment_applications) as total_applications
                    ")->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Total Users:</strong></td>
                            <td><?php echo number_format($dbStats['total_users']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Courses:</strong></td>
                            <td><?php echo number_format($dbStats['total_courses']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Batches:</strong></td>
                            <td><?php echo number_format($dbStats['total_batches']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Applications:</strong></td>
                            <td><?php echo number_format($dbStats['total_applications']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>