<?php
require_once '../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid application ID');
}

$db = (new Database())->getConnection();
$stmt = $db->prepare("SELECT ea.*, u.*, c.course_name, b.batch_name FROM enrollment_applications ea JOIN users u ON ea.user_id = u.user_id JOIN courses c ON ea.course_id = c.course_id JOIN batches b ON ea.batch_id = b.batch_id WHERE ea.application_id = ?");
$stmt->execute([$_GET['id']]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) die('Application not found');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Application Form - <?php echo htmlspecialchars($app['full_name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .field { margin-bottom: 10px; }
        .label { font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h2>Skills Way Vocational Institute</h2>
        <h3>Application Form</h3>
        <p>Application ID: SW<?php echo str_pad($_GET['id'], 6, '0', STR_PAD_LEFT); ?></p>
    </div>
    
    <div class="field"><span class="label">Name:</span> <?php echo htmlspecialchars($app['full_name']); ?></div>
    <div class="field"><span class="label">CNIC:</span> <?php echo htmlspecialchars($app['cnic']); ?></div>
    <div class="field"><span class="label">Email:</span> <?php echo htmlspecialchars($app['email']); ?></div>
    <div class="field"><span class="label">Phone:</span> <?php echo htmlspecialchars($app['phone']); ?></div>
    <div class="field"><span class="label">Course:</span> <?php echo htmlspecialchars($app['course_name']); ?></div>
    <div class="field"><span class="label">Batch:</span> <?php echo htmlspecialchars($app['batch_name']); ?></div>
    <div class="field"><span class="label">Status:</span> <?php echo ucfirst($app['application_status']); ?></div>
    <div class="field"><span class="label">Applied:</span> <?php echo date('F d, Y', strtotime($app['application_date'])); ?></div>
    
    <div class="no-print" style="margin-top: 30px;">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>
</body>
</html>