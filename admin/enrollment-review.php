<?php
/**
 * Enrollment Applications Review
 * File: admin/enrollment-review.php
 */

require_once 'includes/header.php';

// Get filter parameters
$status = $_GET['status'] ?? 'submitted';
$search = $_GET['search'] ?? '';
$course_id = $_GET['course_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT ea.*, u.full_name, u.cnic, u.email, u.phone, 
                 c.course_name, c.course_code,
                 b.batch_name, b.batch_code,
                 reviewer.full_name as reviewer_name
          FROM enrollment_applications ea
          JOIN users u ON ea.user_id = u.user_id
          JOIN courses c ON ea.course_id = c.course_id
          JOIN batches b ON ea.batch_id = b.batch_id
          LEFT JOIN users reviewer ON ea.reviewed_by = reviewer.user_id
          WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if ($status !== 'all') {
    $query .= " AND ea.application_status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR u.cnic LIKE ? OR u.email LIKE ? OR ea.application_id = ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = intval($search);
    $types .= 'sssi';
}

if (!empty($course_id)) {
    $query .= " AND ea.course_id = ?";
    $params[] = $course_id;
    $types .= 'i';
}

if (!empty($date_from)) {
    $query .= " AND DATE(ea.application_date) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(ea.application_date) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Count total records
$countQuery = "SELECT COUNT(*) as total FROM ($query) as t";
$countStmt = $db->prepare($countQuery);
if (!empty($params)) {
    $countStmt->execute($params);
} else {
    $countStmt->execute();
}
$countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
$totalRecords = $countResult['total'];
$totalPages = ceil($totalRecords / $limit);

// Apply pagination
$query .= " ORDER BY ea.application_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Fetch applications
$stmt = $db->prepare($query);
$stmt->execute($params);
$applications = $stmt;

// Get courses for filter dropdown
$coursesQuery = "SELECT course_id, course_name FROM courses WHERE is_active = 1 ORDER BY course_name";
$coursesResult = $db->query($coursesQuery);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <!-- Top Bar -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-file-alt me-2"></i>Enrollment Applications
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="?status=all&export=excel" class="btn btn-sm btn-outline-success me-2">
                <i class="fas fa-file-excel me-1"></i>Export Excel
            </a>
            <a href="?status=all&export=pdf" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-file-pdf me-1"></i>Export PDF
            </a>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Applications</li>
        </ol>
    </nav>
    
    <!-- Stats Overview -->
    <div class="row mb-4">
        <?php
        $statsQuery = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN application_status = 'submitted' THEN 1 END) as submitted,
            COUNT(CASE WHEN application_status = 'under_review' THEN 1 END) as under_review,
            COUNT(CASE WHEN application_status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN application_status = 'rejected' THEN 1 END) as rejected
            FROM enrollment_applications";
        $statsResult = $db->query($statsQuery);
        $appStats = $statsResult->fetch(PDO::FETCH_ASSOC);
        ?>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card border-0 bg-primary bg-opacity-10">
                <div class="card-body text-center p-3">
                    <h3 class="mb-0 text-primary"><?php echo $appStats['total']; ?></h3>
                    <small class="text-muted">Total Applications</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card border-0 bg-warning bg-opacity-10">
                <div class="card-body text-center p-3">
                    <h3 class="mb-0 text-warning"><?php echo $appStats['submitted']; ?></h3>
                    <small class="text-muted">Submitted</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card border-0 bg-info bg-opacity-10">
                <div class="card-body text-center p-3">
                    <h3 class="mb-0 text-info"><?php echo $appStats['under_review']; ?></h3>
                    <small class="text-muted">Under Review</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card border-0 bg-success bg-opacity-10">
                <div class="card-body text-center p-3">
                    <h3 class="mb-0 text-success"><?php echo $appStats['approved']; ?></h3>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card border-0 bg-danger bg-opacity-10">
                <div class="card-body text-center p-3">
                    <h3 class="mb-0 text-danger"><?php echo $appStats['rejected']; ?></h3>
                    <small class="text-muted">Rejected</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card border-0 bg-dark bg-opacity-10">
                <div class="card-body text-center p-3">
                    <h3 class="mb-0 text-dark"><?php echo $totalRecords; ?></h3>
                    <small class="text-muted">Filtered</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="submitted" <?php echo $status == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="under_review" <?php echo $status == 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="waitlisted" <?php echo $status == 'waitlisted' ? 'selected' : ''; ?>>Waitlisted</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Course</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Courses</option>
                        <?php while($course = $coursesResult->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $course['course_id']; ?>" 
                                <?php echo $course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control datepicker" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control datepicker" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, CNIC, Email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                        <a href="enrollment-review.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                        <button type="button" class="btn btn-outline-info ms-auto" data-bs-toggle="modal" data-bs-target="#bulkActionModal">
                            <i class="fas fa-tasks me-1"></i>Bulk Actions
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Applications Table -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary">
                <i class="fas fa-list me-2"></i>Applications List
                <span class="badge bg-primary ms-2"><?php echo $totalRecords; ?> records</span>
            </h6>
        </div>
        <div class="card-body">
            <?php if ($applications->rowCount() > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th width="80">ID</th>
                                <th>Applicant</th>
                                <th>Course</th>
                                <th>Batch</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($app = $applications->fetch(PDO::FETCH_ASSOC)): 
                                $statusColors = [
                                    'submitted' => 'warning',
                                    'under_review' => 'info',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'waitlisted' => 'secondary'
                                ];
                                
                                $paymentColors = [
                                    'pending' => 'warning',
                                    'partial' => 'info',
                                    'paid' => 'success',
                                    'waived' => 'secondary'
                                ];
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="app-checkbox" value="<?php echo $app['application_id']; ?>">
                                    </td>
                                    <td>
                                        <strong>SW<?php echo str_pad($app['application_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="user-avatar bg-light text-primary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0 small"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                                                <small class="text-muted d-block">
                                                    <?php echo htmlspecialchars($app['cnic']); ?>
                                                </small>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($app['email']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['course_code']); ?></strong>
                                        <div class="small text-muted"><?php echo htmlspecialchars($app['course_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($app['batch_code']); ?>
                                        </span>
                                        <div class="small text-muted"><?php echo htmlspecialchars($app['batch_name']); ?></div>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($app['application_date'])); ?>
                                        <div class="small text-muted">
                                            <?php echo date('h:i A', strtotime($app['application_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusColors[$app['application_status']]; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $app['application_status'])); ?>
                                        </span>
                                        <?php if ($app['reviewer_name']): ?>
                                            <div class="small text-muted">
                                                By: <?php echo htmlspecialchars($app['reviewer_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $paymentColors[$app['payment_status']]; ?>">
                                            <?php echo ucfirst($app['payment_status']); ?>
                                        </span>
                                        <?php if ($app['payment_amount'] > 0): ?>
                                            <div class="small">
                                                Rs. <?php echo number_format($app['payment_amount'], 2); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="application-detail.php?id=<?php echo $app['application_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($app['application_status'] == 'submitted'): ?>
                                                <a href="approve-reject.php?action=review&id=<?php echo $app['application_id']; ?>" 
                                                   class="btn btn-sm btn-outline-warning" title="Start Review">
                                                    <i class="fas fa-search"></i>
                                                </a>
                                            <?php elseif ($app['application_status'] == 'under_review'): ?>
                                                <a href="approve-reject.php?id=<?php echo $app['application_id']; ?>" 
                                                   class="btn btn-sm btn-outline-success" title="Approve/Reject">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="sendReminder(<?php echo $app['application_id']; ?>, '<?php echo $app['email']; ?>')"
                                                    title="Send Reminder">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): 
                                if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No applications found</h5>
                    <p class="text-muted">Try changing your filters or check back later.</p>
                    <a href="enrollment-review.php" class="btn btn-primary">
                        <i class="fas fa-redo me-1"></i>Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Bulk Action Modal -->
<div class="modal fade" id="bulkActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkActionForm" method="POST" action="bulk-actions.php">
                    <div class="mb-3">
                        <label class="form-label">Select Action</label>
                        <select name="action" class="form-select" required>
                            <option value="">Choose action...</option>
                            <option value="status_submitted">Mark as Submitted</option>
                            <option value="status_under_review">Mark as Under Review</option>
                            <option value="status_approved">Approve Selected</option>
                            <option value="status_rejected">Reject Selected</option>
                            <option value="send_email">Send Email Notification</option>
                            <option value="export_selected">Export Selected</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Selected Applications</label>
                        <div id="selectedCount" class="alert alert-info py-2">
                            0 applications selected
                        </div>
                        <input type="hidden" name="selected_ids" id="selectedIds">
                    </div>
                    <div id="emailSection" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Email Subject</label>
                            <input type="text" name="email_subject" class="form-control" 
                                   placeholder="Application Status Update">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Message</label>
                            <textarea name="email_message" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="bulkActionForm" class="btn btn-primary">Apply Action</button>
            </div>
        </div>
    </div>
</div>

<script>
// Select All functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.app-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateSelectedCount();
});

// Update selected count
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.app-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = 
        count + ' application' + (count !== 1 ? 's' : '') + ' selected';
    
    // Build comma-separated list of IDs
    const ids = Array.from(checkboxes).map(cb => cb.value).join(',');
    document.getElementById('selectedIds').value = ids;
}

// Attach event to checkboxes
document.querySelectorAll('.app-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

// Show/hide email section
document.querySelector('select[name="action"]').addEventListener('change', function() {
    const emailSection = document.getElementById('emailSection');
    if (this.value === 'send_email') {
        emailSection.classList.remove('d-none');
    } else {
        emailSection.classList.add('d-none');
    }
});

// Send reminder
function sendReminder(appId, email) {
    if (confirm(`Send reminder email to ${email}?`)) {
        fetch('send-reminder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ application_id: appId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reminder sent successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error sending reminder: ' + error);
        });
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>