<?php
session_start();
require_once 'includes/header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: enrollment-review.php');
    exit;
}

$application_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("INSERT INTO payments (application_id, user_id, amount, payment_method, transaction_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $application_id,
        $_POST['user_id'],
        $_POST['amount'],
        $_POST['payment_method'],
        $_POST['transaction_id'],
        $_POST['notes'],
        $_SESSION['user_id']
    ]);
    
    // Update application payment status
    $db->prepare("UPDATE enrollment_applications SET payment_amount = payment_amount + ?, payment_status = ? WHERE application_id = ?")
      ->execute([$_POST['amount'], $_POST['payment_status'], $application_id]);
    
    header("Location: application-detail.php?id=$application_id");
    exit;
}

// Get application details
$stmt = $db->prepare("SELECT ea.*, u.full_name, c.fee_amount FROM enrollment_applications ea JOIN users u ON ea.user_id = u.user_id JOIN courses c ON ea.course_id = c.course_id WHERE ea.application_id = ?");
$stmt->execute([$application_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Record Payment</h1>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Payment for <?php echo htmlspecialchars($app['full_name']); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $app['user_id']; ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                        <small class="text-muted">Course fee: Rs. <?php echo number_format($app['fee_amount'], 2); ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="online">Online Payment</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Transaction ID</label>
                        <input type="text" name="transaction_id" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select" required>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid in Full</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Record Payment</button>
                <a href="application-detail.php?id=<?php echo $application_id; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>