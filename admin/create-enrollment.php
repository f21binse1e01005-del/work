<?php

/**
 * Create Enrollment Script
 * Converts an approved application into an active enrollment
 */

require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();

// Get application ID
$application_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$application_id) {
    echo "Invalid application ID";
    exit;
}

try {
    $db->beginTransaction();

    // 1. Get application details
    $stmt = $db->prepare("
        SELECT * FROM enrollment_applications 
        WHERE application_id = ? AND application_status = 'approved'
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        throw new Exception("Application not found or not approved.");
    }

    // 2. Check if already enrolled
    $stmt = $db->prepare("SELECT enrollment_id FROM enrollments WHERE application_id = ?");
    $stmt->execute([$application_id]);
    if ($stmt->fetch()) {
        throw new Exception("Student is already enrolled for this application.");
    }

    // 3. Create enrollment record
    $stmt = $db->prepare("
        INSERT INTO enrollments (application_id, batch_id, enrollment_status, enrollment_date, notes)
        VALUES (?, ?, 'active', NOW(), 'Enrolled via Admin Panel')
    ");
    $stmt->execute([$application_id, $application['batch_id']]);

    // 4. Update application status (optional, if you want to distinguish enrolled vs just approved)
    // keeping it 'approved' or redundant 'enrolled' depending on logic. 
    // Let's keep it approved but maybe add a note or just rely on the existence of enrollment record.

    $db->commit();

    // Redirect back to application detail with success
    header("Location: application-detail.php?id=$application_id&enrolled=success");
    exit;
} catch (Exception $e) {
    $db->rollBack();
    die("Error: " . $e->getMessage());
}
