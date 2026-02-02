<?php
/**
 * Students Management - CRUD Operations
 * File: admin/students.php
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && isset($_POST['user_id'])) {
        $stmt = $db->prepare("UPDATE users SET account_status = 'inactive' WHERE user_id = ? AND user_type = 'student'");
        if ($stmt->execute([$_POST['user_id']])) {
            $message = 'Student deactivated successfully';
            $messageType = 'success';
        }
    }
    
    if ($action === 'activate' && isset($_POST['user_id'])) {
        $stmt = $db->prepare("UPDATE users SET account_status = 'active' WHERE user_id = ? AND user_type = 'student'");
        if ($stmt->execute([$_POST['user_id']])) {
            $message = 'Student activated successfully';
            $messageType = 'success';
        }
    }
}

// Get students with search
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';

$query = "SELECT u.*, up.parent_name, up.parent_phone, up.address 
          FROM users u 
          LEFT JOIN user_profiles up ON u.user_id = up.user_id 
          WHERE u.user_type = 'student'";

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.cnic LIKE ?)";
}
if ($status !== 'all') {
    $query .= " AND u.account_status = ?";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$params = [];
if ($search) {
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}
if ($status !== 'all') {
    $params[] = $status;
}

$stmt->execute($params);
$students = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-users me-2"></i>Students Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Add Student
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Student added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search students..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Students List (<?php echo count($students); ?> records)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student Info</th>
                            <th>Contact</th>
                            <th>Parent Info</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($student['full_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($student['email']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($student['parent_name'] ?? 'N/A'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($student['parent_phone'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $student['account_status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($student['account_status']); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo date('M d, Y', strtotime($student['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" onclick="viewStudent(<?php echo $student['user_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($student['account_status'] === 'active'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this student?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                            <button type="submit" class="btn btn-outline-success">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
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
function viewStudent(userId) {
    // Simple modal or redirect to student details
    window.location.href = `student-detail.php?id=${userId}`;
}
</script>

<?php require_once 'includes/footer.php'; ?>