<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: enrollment-review.php');
    exit;
}

$db = (new Database())->getConnection();
$application_id = $_POST['application_id'];
$action = $_POST['action'];

if ($action === 'add_note') {
    $updates = [];
    $params = [];
    
    if (!empty($_POST['review_notes'])) {
        $updates[] = "review_notes = ?";
        $params[] = $_POST['review_notes'];
    }
    
    if (!empty($_POST['application_status'])) {
        $updates[] = "application_status = ?, reviewed_by = ?, review_date = NOW()";
        $params[] = $_POST['application_status'];
        $params[] = $_SESSION['user_id'];
    }
    
    if (!empty($_POST['payment_status'])) {
        $updates[] = "payment_status = ?";
        $params[] = $_POST['payment_status'];
    }
    
    if (!empty($updates)) {
        $params[] = $application_id;
        $sql = "UPDATE enrollment_applications SET " . implode(', ', $updates) . " WHERE application_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
}

header("Location: application-detail.php?id=$application_id");
exit;
?>