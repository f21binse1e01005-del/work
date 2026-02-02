<?php
/**
 * Admin Payments Management
 * File: admin/payments.php
 */

// Handle payment updates BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    require_once '../config/database.php';
    
    $db = (new Database())->getConnection();
    
    $application_id = $_POST['application_id'];
    $payment_amount = $_POST['payment_amount'];
    $payment_status = $_POST['payment_status'];
    
    $sql = "UPDATE enrollment_applications SET payment_amount = ?, payment_status = ? WHERE application_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$payment_amount, $payment_status, $application_id]);
    
    header('Location: payments.php?updated=1');
    exit;
}

// Handle new payment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    require_once '../config/database.php';
    
    $db = (new Database())->getConnection();
    
    $user_id = $_POST['user_id'];
    $course_id = $_POST['course_id'];
    $batch_id = $_POST['batch_id'];
    $payment_amount = $_POST['payment_amount'];
    $payment_status = $_POST['payment_status'];
    
    // Create enrollment application with payment
    $sql = "INSERT INTO enrollment_applications (user_id, course_id, batch_id, payment_amount, payment_status, application_status) 
            VALUES (?, ?, ?, ?, ?, 'approved')";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $course_id, $batch_id, $payment_amount, $payment_status]);
    
    header('Location: payments.php?added=1');
    exit;
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once '../config/database.php';
    
    $db = (new Database())->getConnection();
    
    $paymentsQuery = "
        SELECT ea.application_id, ea.payment_amount, ea.payment_status, ea.application_date,
               u.full_name, u.cnic, c.course_name, b.batch_name
        FROM enrollment_applications ea
        JOIN users u ON ea.user_id = u.user_id
        JOIN courses c ON ea.course_id = c.course_id
        JOIN batches b ON ea.batch_id = b.batch_id
        WHERE ea.application_status = 'approved'
        ORDER BY ea.application_date DESC
    ";
    $payments = $db->query($paymentsQuery);
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="payments_report_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>Application ID</th><th>Student Name</th><th>CNIC</th><th>Course</th><th>Batch</th><th>Amount (Rs.)</th><th>Status</th><th>Date</th></tr>";
    
    while($payment = $payments->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . str_pad($payment['application_id'], 6, '0', STR_PAD_LEFT) . "</td>";
        echo "<td>" . htmlspecialchars($payment['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($payment['cnic']) . "</td>";
        echo "<td>" . htmlspecialchars($payment['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($payment['batch_name']) . "</td>";
        echo "<td>" . number_format($payment['payment_amount'], 2) . "</td>";
        echo "<td>" . ucfirst($payment['payment_status']) . "</td>";
        echo "<td>" . date('M d, Y', strtotime($payment['application_date'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

require_once 'includes/header.php';

// Get students for dropdown
$studentsQuery = "SELECT user_id, full_name FROM users WHERE user_type = 'student' AND account_status = 'active' ORDER BY full_name";
$students = $db->query($studentsQuery);

// Get courses for dropdown
$coursesQuery = "SELECT course_id, course_name FROM courses WHERE is_active = 1 ORDER BY course_name";
$courses = $db->query($coursesQuery);

// Get batches for dropdown
$batchesQuery = "SELECT batch_id, batch_name, course_id FROM batches WHERE status IN ('upcoming', 'ongoing') ORDER BY batch_name";
$batches = $db->query($batchesQuery);

// Get payment records
$paymentsQuery = "
    SELECT ea.application_id, ea.payment_amount, ea.payment_status, ea.application_date,
           u.full_name, u.cnic, c.course_name, b.batch_name
    FROM enrollment_applications ea
    JOIN users u ON ea.user_id = u.user_id
    JOIN courses c ON ea.course_id = c.course_id
    JOIN batches b ON ea.batch_id = b.batch_id
    WHERE ea.application_status = 'approved'
    ORDER BY ea.application_date DESC
";
$payments = $db->query($paymentsQuery);

// Get payment statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN payment_status = 'paid' THEN payment_amount ELSE 0 END) as total_received,
        SUM(CASE WHEN payment_status = 'pending' THEN payment_amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN DATE(application_date) = CURDATE() AND payment_status = 'paid' THEN payment_amount ELSE 0 END) as today_received
    FROM enrollment_applications 
    WHERE application_status = 'approved'
";
$paymentStats = $db->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-credit-card me-2"></i>Payment Management</h1>
        <div class="btn-group">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="fas fa-plus me-1"></i>Add Payment
            </button>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print Report
            </button>
            <a href="?export=excel" class="btn btn-outline-success">
                <i class="fas fa-download me-1"></i>Export Excel
            </a>
        </div>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Payment updated successfully!</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">Payment added successfully!</div>
    <?php endif; ?>

    <!-- Payment Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Received</h6>
                            <h4>Rs. <?php echo number_format($paymentStats['total_received'], 2); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Pending</h6>
                            <h4>Rs. <?php echo number_format($paymentStats['total_pending'], 2); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Today's Collection</h6>
                            <h4>Rs. <?php echo number_format($paymentStats['today_received'], 2); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-day fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Payments</h6>
                            <h4><?php echo number_format($paymentStats['total_payments']); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-receipt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="m-0">Payment Records</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($payment = $payments->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td>#<?php echo str_pad($payment['application_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo $payment['cnic']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($payment['course_name']); ?>
                                        <br><small class="text-muted"><?php echo $payment['batch_name']; ?></small>
                                    </div>
                                </td>
                                <td><strong>Rs. <?php echo number_format($payment['payment_amount'], 2); ?></strong></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'pending' => 'bg-warning',
                                        'partial' => 'bg-info',
                                        'paid' => 'bg-success',
                                        'waived' => 'bg-secondary'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $statusClass[$payment['payment_status']]; ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($payment['application_date'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewPayment(<?php echo $payment['application_id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="editPayment(<?php echo $payment['application_id']; ?>, '<?php echo $payment['payment_amount']; ?>', '<?php echo $payment['payment_status']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($payment['payment_status'] !== 'paid'): ?>
                                            <button class="btn btn-outline-success" onclick="markPaid(<?php echo $payment['application_id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_payment" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Student</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select Student</option>
                            <?php 
                            $studentsForModal = $db->query($studentsQuery);
                            while($student = $studentsForModal->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $student['user_id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" id="add_course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php 
                            $coursesForModal = $db->query($coursesQuery);
                            while($course = $coursesForModal->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $course['course_id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Batch</label>
                        <select name="batch_id" id="add_batch_id" class="form-select" required>
                            <option value="">Select Batch</option>
                            <?php 
                            $batchesForModal = $db->query($batchesQuery);
                            while($batch = $batchesForModal->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $batch['batch_id']; ?>" data-course="<?php echo $batch['course_id']; ?>">
                                    <?php echo htmlspecialchars($batch['batch_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Amount (Rs.)</label>
                        <input type="number" name="payment_amount" class="form-control" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                            <option value="waived">Waived</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="edit_application_id">
                    <input type="hidden" name="update_payment" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Amount (Rs.)</label>
                        <input type="number" name="payment_amount" id="edit_payment_amount" class="form-control" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" id="edit_payment_status" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                            <option value="waived">Waived</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter batches by course
document.getElementById('add_course_id').addEventListener('change', function() {
    const courseId = this.value;
    const batchSelect = document.getElementById('add_batch_id');
    const batchOptions = batchSelect.querySelectorAll('option');
    
    batchOptions.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else if (option.dataset.course === courseId) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
    
    batchSelect.value = '';
});

function editPayment(applicationId, amount, status) {
    document.getElementById('edit_application_id').value = applicationId;
    document.getElementById('edit_payment_amount').value = amount;
    document.getElementById('edit_payment_status').value = status;
    
    var modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
    modal.show();
}

function viewPayment(applicationId) {
    alert('View payment details for application #' + applicationId);
}

function markPaid(applicationId) {
    if (confirm('Mark this payment as paid?')) {
        document.getElementById('edit_application_id').value = applicationId;
        document.getElementById('edit_payment_status').value = 'paid';
        document.querySelector('input[name="update_payment"]').form.submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>