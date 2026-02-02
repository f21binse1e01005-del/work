<?php
/**
 * Application Detail View
 * Enhanced Version with better security, UX, and performance
 * File: admin/application-detail.php
 */

require_once 'includes/header.php';
require_once 'includes/functions.php'; // Added for helper functions

// Security: Validate and sanitize input
$application_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$application_id || $application_id <= 0) {
    setFlashMessage('error', 'Invalid application ID provided.');
    redirect('enrollment-review.php');
}

// Status configurations
$statusConfig = [
    'submitted' => ['color' => 'warning', 'icon' => 'fas fa-paper-plane', 'label' => 'Submitted'],
    'under_review' => ['color' => 'info', 'icon' => 'fas fa-search', 'label' => 'Under Review'],
    'approved' => ['color' => 'success', 'icon' => 'fas fa-check-circle', 'label' => 'Approved'],
    'rejected' => ['color' => 'danger', 'icon' => 'fas fa-times-circle', 'label' => 'Rejected'],
    'waitlisted' => ['color' => 'secondary', 'icon' => 'fas fa-clock', 'label' => 'Waitlisted']
];

$paymentConfig = [
    'pending' => ['color' => 'warning', 'icon' => 'fas fa-clock'],
    'partial' => ['color' => 'info', 'icon' => 'fas fa-money-bill-wave'],
    'paid' => ['color' => 'success', 'icon' => 'fas fa-check-circle'],
    'waived' => ['color' => 'secondary', 'icon' => 'fas fa-hand-holding-usd']
];

try {
    // Fetch application details with optimized query
    $query = "
        SELECT 
            ea.*, 
            u.*, 
            COALESCE(up.*, JSON_OBJECT()) as profile_data,
            c.course_name, c.course_code, c.fee_amount, c.duration_months,
            b.batch_name, b.batch_code, b.start_date, b.end_date,
            r.full_name as reviewer_name, r.email as reviewer_email,
            e.enrollment_id, e.enrollment_date, e.enrollment_status,
            COALESCE(ea.payment_amount, 0) as paid_amount,
            (c.fee_amount - COALESCE(ea.payment_amount, 0)) as balance_due,
            COUNT(p.payment_id) as payment_count,
            MAX(p.payment_date) as last_payment_date
        FROM enrollment_applications ea
        INNER JOIN users u ON ea.user_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        INNER JOIN courses c ON ea.course_id = c.course_id
        INNER JOIN batches b ON ea.batch_id = b.batch_id
        LEFT JOIN users r ON ea.reviewed_by = r.user_id
        LEFT JOIN enrollments e ON ea.application_id = e.application_id
        LEFT JOIN payments p ON ea.application_id = p.application_id
        WHERE ea.application_id = ?
        GROUP BY ea.application_id
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        throw new Exception("Application not found");
    }
    
    // Fetch related data in parallel (if supported) or sequentially
    // Similar applications
    $similarStmt = $db->prepare("
        SELECT ea.application_id, u.full_name, ea.application_status, 
               ea.application_date, c.course_code,
               DATEDIFF(NOW(), ea.application_date) as days_ago
        FROM enrollment_applications ea
        INNER JOIN users u ON ea.user_id = u.user_id
        INNER JOIN courses c ON ea.course_id = c.course_id
        WHERE ea.course_id = ? AND ea.application_id != ?
        ORDER BY ea.application_date DESC
        LIMIT 5
    ");
    $similarStmt->execute([$application['course_id'], $application_id]);
    $similarApplications = $similarStmt->fetchAll();
    
    // Payment history with more details
    $paymentStmt = $db->prepare("
        SELECT p.*, u.full_name as recorded_by,
               DATE_FORMAT(p.payment_date, '%Y-%m-%d %H:%i:%s') as formatted_date
        FROM payments p
        LEFT JOIN users u ON p.recorded_by = u.user_id
        WHERE p.application_id = ?
        ORDER BY p.payment_date DESC
    ");
    $paymentStmt->execute([$application_id]);
    $payments = $paymentStmt->fetchAll();
    
    // Activity logs
    $activityStmt = $db->prepare("
        SELECT activity_type, description, performed_at, ip_address
        FROM user_activity_logs
        WHERE user_id = ? AND activity_type LIKE '%application%'
        ORDER BY performed_at DESC
        LIMIT 10
    ");
    $activityStmt->execute([$application['user_id']]);
    $activities = $activityStmt->fetchAll();
    
    // Calculate age if DOB exists
    $age = null;
    if (!empty($application['date_of_birth'])) {
        $dob = new DateTime($application['date_of_birth']);
        $today = new DateTime();
        $age = $dob->diff($today)->y;
    }
    
} catch (Exception $e) {
    logError($e->getMessage());
    setFlashMessage('error', 'Failed to load application details: ' . $e->getMessage());
    redirect('enrollment-review.php');
}

// Format dates for display
$appDate = !empty($application['application_date']) ? 
    date('F d, Y g:i A', strtotime($application['application_date'])) : 'N/A';
$reviewDate = !empty($application['review_date']) ? 
    date('F d, Y g:i A', strtotime($application['review_date'])) : null;
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" id="application-detail">
    <!-- Flash Messages -->
    <?php if (hasFlashMessage()): ?>
        <div class="alert alert-<?php echo getFlashType(); ?> alert-dismissible fade show mt-3" role="alert">
            <?php echo getFlashMessage(); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Top Bar -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div class="d-flex align-items-center">
            <h1 class="h2 mb-0 me-3">
                <i class="fas fa-file-invoice me-2 text-primary"></i>
                Application Details
            </h1>
            <span class="badge bg-light text-dark fs-6 border">
                SW<?php echo str_pad($application_id, 6, '0', STR_PAD_LEFT); ?>
            </span>
        </div>
        
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="enrollment-review.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
            
            <div class="btn-group me-2">
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                
                <?php if (in_array($application['application_status'], ['submitted', 'under_review'])): ?>
                    <a href="approve-reject.php?id=<?php echo $application_id; ?>" 
                       class="btn btn-sm btn-<?php echo $application['application_status'] === 'submitted' ? 'warning' : 'success'; ?>">
                        <i class="fas fa-<?php echo $application['application_status'] === 'submitted' ? 'search' : 'check'; ?> me-1"></i>
                        <?php echo $application['application_status'] === 'submitted' ? 'Start Review' : 'Approve/Reject'; ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" 
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#" onclick="sendEmail(<?php echo $application_id; ?>)">
                            <i class="fas fa-envelope me-2 text-primary"></i>Send Email
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#" onclick="sendSMS(<?php echo $application_id; ?>)">
                            <i class="fas fa-sms me-2 text-info"></i>Send SMS
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#" onclick="generateCredentials(<?php echo $application_id; ?>)">
                            <i class="fas fa-key me-2 text-warning"></i>Generate Credentials
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="record-payment.php?id=<?php echo $application_id; ?>">
                            <i class="fas fa-money-bill-wave me-2 text-success"></i>Record Payment
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" 
                           onclick="confirmAction('delete', <?php echo $application_id; ?>)">
                            <i class="fas fa-trash me-2"></i>Delete Application
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Status Banner -->
    <div class="alert alert-<?php echo $statusConfig[$application['application_status']]['color']; ?> 
                d-flex align-items-center justify-content-between" role="alert">
        <div class="d-flex align-items-center">
            <i class="<?php echo $statusConfig[$application['application_status']]['icon']; ?> fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading mb-1">
                    <?php echo $statusConfig[$application['application_status']]['label']; ?>
                </h5>
                <?php if ($reviewDate): ?>
                    <p class="mb-0 small">
                        Reviewed by <strong><?php echo htmlspecialchars($application['reviewer_name']); ?></strong>
                        on <?php echo $reviewDate; ?>
                    </p>
                <?php else: ?>
                    <p class="mb-0 small">Awaiting review</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($application['enrollment_id']): ?>
            <div>
                <span class="badge bg-success fs-6 p-2 rounded-pill">
                    <i class="fas fa-user-check me-1"></i>ENROLLED
                </span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Progress Indicator -->
    <div class="progress mb-4" style="height: 8px;">
        <?php
        $progress = [
            'submitted' => 25,
            'under_review' => 50,
            'approved' => 75,
            'enrolled' => 100
        ];
        $currentProgress = $progress[$application['application_status']] ?? 
                          ($application['enrollment_id'] ? 100 : 0);
        ?>
        <div class="progress-bar progress-bar-striped progress-bar-animated" 
             role="progressbar" 
             style="width: <?php echo $currentProgress; ?>%"
             aria-valuenow="<?php echo $currentProgress; ?>" 
             aria-valuemin="0" 
             aria-valuemax="100">
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Applicant Information Card -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-circle me-2 text-primary"></i>Applicant Information
                    </h5>
                    <a href="edit-user.php?id=<?php echo $application['user_id']; ?>" 
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Personal Info -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <div class="avatar-placeholder bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 80px; height: 80px;">
                                        <i class="fas fa-user fa-2x text-secondary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="mb-1"><?php echo htmlspecialchars($application['full_name']); ?></h4>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-id-card me-1"></i>
                                        <?php echo formatCNIC($application['cnic'] ?? ''); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="list-group list-group-flush">
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><i class="fas fa-envelope me-2 text-muted"></i>Email</span>
                                    <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>" 
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($application['email']); ?>
                                    </a>
                                </div>
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><i class="fas fa-phone me-2 text-muted"></i>Phone</span>
                                    <a href="tel:<?php echo htmlspecialchars($application['phone']); ?>" 
                                       class="text-decoration-none">
                                        <?php echo formatPhone($application['phone'] ?? ''); ?>
                                    </a>
                                </div>
                                <?php if (!empty($application['date_of_birth'])): ?>
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><i class="fas fa-birthday-cake me-2 text-muted"></i>Date of Birth</span>
                                    <span>
                                        <?php echo date('M d, Y', strtotime($application['date_of_birth'])); ?>
                                        <?php if ($age): ?>
                                            <span class="badge bg-info ms-2"><?php echo $age; ?> years</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($application['gender'])): ?>
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><i class="fas fa-venus-mars me-2 text-muted"></i>Gender</span>
                                    <span><?php echo ucfirst($application['gender']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="col-md-6">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($application['education_level'])): ?>
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><i class="fas fa-graduation-cap me-2 text-muted"></i>Education</span>
                                    <span><?php echo htmlspecialchars($application['education_level']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($application['address'])): ?>
                                <div class="list-group-item px-0">
                                    <div class="mb-2">
                                        <i class="fas fa-home me-2 text-muted"></i>
                                        <strong>Address</strong>
                                    </div>
                                    <p class="mb-0 small text-muted"><?php echo nl2br(htmlspecialchars($application['address'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($application['parent_name'])): ?>
                                <div class="list-group-item px-0">
                                    <h6 class="mb-2">
                                        <i class="fas fa-users me-2 text-muted"></i>Parent/Guardian
                                    </h6>
                                    <div class="row small">
                                        <div class="col-6">
                                            <span class="text-muted">Name:</span><br>
                                            <?php echo htmlspecialchars($application['parent_name']); ?>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted">Phone:</span><br>
                                            <?php echo formatPhone($application['parent_phone'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Previous Experience -->
                    <?php if (!empty($application['previous_experience'])): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="mb-2">
                            <i class="fas fa-briefcase me-2 text-muted"></i>Previous Experience
                        </h6>
                        <div class="bg-light p-3 rounded small">
                            <?php echo nl2br(htmlspecialchars($application['previous_experience'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Course & Financial Information -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2 text-success"></i>Course & Financial Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Course Details -->
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-3">
                                        <i class="fas fa-book-open me-2"></i>Course Information
                                    </h6>
                                    <div class="mb-3">
                                        <label class="small text-muted">Course</label>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($application['course_name']); ?></h5>
                                        <p class="text-muted mb-0">
                                            <code><?php echo htmlspecialchars($application['course_code']); ?></code>
                                            • <?php echo $application['duration_months']; ?> months
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted">Batch</label>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-light text-dark me-2">
                                                <?php echo htmlspecialchars($application['batch_code']); ?>
                                            </span>
                                            <span><?php echo htmlspecialchars($application['batch_name']); ?></span>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('M d, Y', strtotime($application['start_date'])); ?> 
                                            - 
                                            <?php echo date('M d, Y', strtotime($application['end_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Financial Details -->
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-3">
                                        <i class="fas fa-money-bill-wave me-2"></i>Financial Summary
                                    </h6>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="p-2 bg-light rounded">
                                                <div class="small text-muted">Total Fee</div>
                                                <div class="h5 mb-0">Rs. <?php echo number_format($application['fee_amount'], 2); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 bg-light rounded">
                                                <div class="small text-muted">Paid</div>
                                                <div class="h5 mb-0 text-success">Rs. <?php echo number_format($application['paid_amount'], 2); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 bg-light rounded">
                                                <div class="small text-muted">Balance</div>
                                                <div class="h5 mb-0 <?php echo $application['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                    Rs. <?php echo number_format($application['balance_due'], 2); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 bg-light rounded">
                                                <div class="small text-muted">Status</div>
                                                <div>
                                                    <span class="badge bg-<?php echo $paymentConfig[$application['payment_status']]['color']; ?>">
                                                        <i class="<?php echo $paymentConfig[$application['payment_status']]['icon']; ?> me-1"></i>
                                                        <?php echo ucfirst($application['payment_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="record-payment.php?id=<?php echo $application_id; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-plus me-1"></i>Record Payment
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment History -->
                    <?php if (count($payments) > 0): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="mb-3">
                            <i class="fas fa-history me-2 text-muted"></i>Payment History
                            <span class="badge bg-secondary ms-2"><?php echo count($payments); ?></span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Recorded By</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <div class="small"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div>
                                            <div class="text-muted"><?php echo date('g:i A', strtotime($payment['payment_date'])); ?></div>
                                        </td>
                                        <td class="fw-bold">
                                            Rs. <?php echo number_format($payment['amount'], 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo strtoupper($payment['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code class="small"><?php echo htmlspecialchars($payment['transaction_id']); ?></code>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($payment['recorded_by'] ?? 'System'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewReceipt(<?php echo $payment['payment_id']; ?>)"
                                                        title="View Receipt">
                                                    <i class="fas fa-receipt"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" 
                                                        onclick="sendReceipt(<?php echo $payment['payment_id']; ?>)"
                                                        title="Email Receipt">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Review & Notes Section -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2 text-info"></i>Review & Notes
                    </h5>
                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" data-bs-target="#notesForm">
                        <i class="fas fa-plus me-1"></i>Add Note
                    </button>
                </div>
                <div class="card-body">
                    <!-- Existing Notes -->
                    <?php if (!empty($application['review_notes'])): ?>
                    <div class="alert alert-light border">
                        <h6 class="mb-2">
                            <i class="fas fa-sticky-note me-2 text-warning"></i>Review Notes
                        </h6>
                        <div class="bg-white p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($application['review_notes'])); ?>
                        </div>
                        <?php if ($reviewDate): ?>
                        <div class="text-end mt-2 small text-muted">
                            <?php echo $reviewDate; ?>
                            <?php if (!empty($application['reviewer_name'])): ?>
                                by <?php echo htmlspecialchars($application['reviewer_name']); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add Note Form (Collapsible) -->
                    <div class="collapse <?php echo empty($application['review_notes']) ? 'show' : ''; ?>" id="notesForm">
                        <form method="POST" action="update-application.php" id="notesFormElement">
                            <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                            <input type="hidden" name="action" value="update_notes">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Update Application</label>
                                <textarea name="review_notes" class="form-control" rows="4" 
                                          placeholder="Add review notes or updates..." 
                                          required></textarea>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="application_status" class="form-select">
                                        <option value="" disabled selected>Select new status</option>
                                        <?php foreach($statusConfig as $key => $config): ?>
                                            <?php if ($key != $application['application_status']): ?>
                                                <option value="<?php echo $key; ?>" 
                                                        data-color="<?php echo $config['color']; ?>">
                                                    <?php echo $config['label']; ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Status</label>
                                    <select name="payment_status" class="form-select">
                                        <option value="" disabled selected>Update payment</option>
                                        <?php foreach($paymentConfig as $key => $config): ?>
                                            <?php if ($key != $application['payment_status']): ?>
                                                <option value="<?php echo $key; ?>">
                                                    <?php echo ucfirst($key); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-3 d-flex justify-content-end gap-2">
                                <button type="reset" class="btn btn-outline-secondary">Clear</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Updates
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Activity Timeline -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="mb-3">
                            <i class="fas fa-stream me-2 text-muted"></i>Activity Timeline
                        </h6>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Application Submitted</h6>
                                    <p class="text-muted mb-0 small">
                                        <?php echo $appDate; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php foreach($activities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-<?php echo getActivityColor($activity['activity_type']); ?>"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1"><?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></h6>
                                    <p class="text-muted mb-0 small">
                                        <?php echo date('M d, Y g:i A', strtotime($activity['performed_at'])); ?>
                                        <?php if (!empty($activity['description'])): ?>
                                            <br><?php echo htmlspecialchars($activity['description']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if ($application['enrollment_date']): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Enrollment Created</h6>
                                    <p class="text-muted mb-0 small">
                                        <?php echo date('F d, Y', strtotime($application['enrollment_date'])); ?>
                                        <span class="badge bg-light text-dark ms-2">
                                            <?php echo $application['enrollment_status']; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if (!$application['enrollment_id'] && $application['application_status'] == 'approved'): ?>
                            <a href="create-enrollment.php?id=<?php echo $application_id; ?>" 
                               class="btn btn-success btn-lg py-3">
                                <i class="fas fa-user-check me-2"></i>Create Enrollment
                            </a>
                        <?php endif; ?>
                        
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="sendEmail(<?php echo $application_id; ?>)">
                                <i class="fas fa-envelope me-2"></i>Email
                            </button>
                            <button class="btn btn-info" onclick="sendSMS(<?php echo $application_id; ?>)">
                                <i class="fas fa-sms me-2"></i>SMS
                            </button>
                        </div>
                        
                        <button class="btn btn-outline-primary" onclick="generateCredentials(<?php echo $application_id; ?>)">
                            <i class="fas fa-key me-2"></i>Generate Credentials
                        </button>
                        
                        <a href="print-application.php?id=<?php echo $application_id; ?>" 
                           target="_blank" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-2"></i>Print Application
                        </a>
                        
                        <button class="btn btn-outline-success" onclick="recordPaymentModal()">
                            <i class="fas fa-money-bill-wave me-2"></i>Quick Payment
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Similar Applications -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-users me-2 text-secondary"></i>Similar Applications
                        </span>
                        <span class="badge bg-light text-dark"><?php echo count($similarApplications); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($similarApplications) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($similarApplications as $similar): ?>
                                <a href="application-detail.php?id=<?php echo $similar['application_id']; ?>" 
                                   class="list-group-item list-group-item-action border-0 py-3">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div class="me-2">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($similar['full_name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($similar['course_code']); ?> 
                                                • <?php echo $similar['days_ago'] <= 0 ? 'Today' : $similar['days_ago'] . ' days ago'; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $statusConfig[$similar['application_status']]['color']; ?>">
                                            <?php echo $statusConfig[$similar['application_status']]['label']; ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No similar applications found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Application Metadata -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-dark"></i>Application Details
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="50%"><small>Application ID:</small></th>
                            <td class="text-end">
                                <code>SW<?php echo str_pad($application_id, 6, '0', STR_PAD_LEFT); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th><small>User ID:</small></th>
                            <td class="text-end">
                                <code>U<?php echo str_pad($application['user_id'], 6, '0', STR_PAD_LEFT); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th><small>Submitted:</small></th>
                            <td class="text-end">
                                <small><?php echo $appDate; ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th><small>Last Updated:</small></th>
                            <td class="text-end">
                                <small><?php echo $reviewDate ?: 'Not reviewed'; ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th><small>Payments:</small></th>
                            <td class="text-end">
                                <span class="badge bg-light text-dark">
                                    <?php echo $application['payment_count']; ?> records
                                </span>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="mb-2 small fw-bold text-muted">System Tools</h6>
                        <div class="d-grid gap-2">
                            <a href="audit-log.php?type=application&id=<?php echo $application_id; ?>" 
                               class="btn btn-sm btn-outline-info">
                                <i class="fas fa-history me-1"></i>View Audit Log
                            </a>
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="duplicateApplication(<?php echo $application_id; ?>)">
                                <i class="fas fa-copy me-1"></i>Duplicate Application
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="confirmAction('archive', <?php echo $application_id; ?>)">
                                <i class="fas fa-archive me-1"></i>Archive Application
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Components -->
<?php include 'includes/modals/email-modal.php'; ?>
<?php include 'includes/modals/sms-modal.php'; ?>
<?php include 'includes/modals/payment-modal.php'; ?>

<style>
/* Enhanced Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
    border-left: 2px solid #dee2e6;
}

.timeline-item {
    position: relative;
    padding-bottom: 25px;
}

.timeline-marker {
    position: absolute;
    left: -36px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    padding-left: 15px;
}

.timeline-content h6 {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

/* Avatar Placeholder */
.avatar-placeholder {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

/* Status Badges */
.badge {
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* Progress Bar Animation */
@keyframes progress-bar-stripes {
    from { background-position: 1rem 0; }
    to { background-position: 0 0; }
}

.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

/* Print Styles */
@media print {
    .btn, .dropdown, .form-control, .collapse, .modal {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    
    .alert {
        border: 1px solid currentColor !important;
    }
}
</style>

<script>
// Enhanced JavaScript with better error handling and UX
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Form validation
    const notesForm = document.getElementById('notesFormElement');
    if (notesForm) {
        notesForm.addEventListener('submit', function(e) {
            const status = this.querySelector('select[name="application_status"]');
            const notes = this.querySelector('textarea[name="review_notes"]');
            
            if (!notes.value.trim() && !status.value) {
                e.preventDefault();
                showToast('Please add notes or change status', 'warning');
            }
        });
    }
    
    // Auto-save draft
    let draftTimer;
    const notesTextarea = document.querySelector('textarea[name="review_notes"]');
    if (notesTextarea) {
        notesTextarea.addEventListener('input', function() {
            clearTimeout(draftTimer);
            draftTimer = setTimeout(() => {
                saveDraft();
            }, 2000);
        });
    }
});

// Enhanced functions with better feedback
function sendEmail(appId) {
    fetch('get-email-template.php?type=application_update&id=' + appId)
        .then(response => response.json())
        .then(template => {
            const modal = new bootstrap.Modal(document.getElementById('emailModal'));
            document.getElementById('emailSubject').value = template.subject;
            document.getElementById('emailMessage').value = template.message;
            document.getElementById('emailAppId').value = appId;
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load email template', 'error');
        });
}

function sendSMS(appId) {
    const modal = new bootstrap.Modal(document.getElementById('smsModal'));
    document.getElementById('smsAppId').value = appId;
    document.getElementById('smsPhone').value = '<?php echo $application["phone"]; ?>';
    modal.show();
}

function generateCredentials(appId) {
    if (!confirm('Generate login credentials for this applicant? An email will be sent with the credentials.')) {
        return;
    }
    
    showLoading();
    fetch('generate-credentials.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo generateCSRFToken(); ?>'
        },
        body: JSON.stringify({ 
            application_id: appId,
            send_email: true 
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Credentials generated and sent via email', 'success');
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('Network error occurred', 'error');
        console.error('Error:', error);
    });
}

function confirmAction(action, appId) {
    const messages = {
        'delete': 'Are you sure you want to delete this application? This action cannot be undone.',
        'archive': 'Archive this application? It will be moved to the archive.',
        'reject': 'Reject this application? The applicant will be notified.'
    };
    
    const confirmations = {
        'delete': () => window.location.href = 'delete-application.php?id=' + appId,
        'archive': () => archiveApplication(appId),
        'reject': () => updateStatus(appId, 'rejected')
    };
    
    if (confirm(messages[action])) {
        confirmations[action]();
    }
}

function viewReceipt(paymentId) {
    window.open('receipt.php?id=' + paymentId, '_blank', 'width=800,height=600');
}

function sendReceipt(paymentId) {
    fetch('send-receipt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ payment_id: paymentId })
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
    });
}

function duplicateApplication(appId) {
    if (confirm('Duplicate this application for a different course/batch?')) {
        showLoading();
        window.location.href = 'duplicate-application.php?id=' + appId;
    }
}

function recordPaymentModal() {
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    document.getElementById('paymentAppId').value = <?php echo $application_id; ?>;
    document.getElementById('paymentBalance').value = <?php echo $application['balance_due']; ?>;
    modal.show();
}

// Utility functions
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${getToastIcon(type)} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    document.body.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

function getToastIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function showLoading() {
    const loader = document.createElement('div');
    loader.id = 'global-loader';
    loader.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
    loader.style.cssText = 'background: rgba(255,255,255,0.8); z-index: 9999;';
    loader.innerHTML = `
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
    `;
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.getElementById('global-loader');
    if (loader) loader.remove();
}

function saveDraft() {
    const formData = new FormData(document.getElementById('notesFormElement'));
    formData.set('action', 'save_draft');
    
    fetch('save-draft.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Draft saved', 'success');
        }
    });
}
</script>

<?php
require_once 'includes/footer.php';
?>