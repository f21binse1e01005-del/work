<?php
/**
 * AJAX endpoint to get batches for a course
 */

// Prevent any output before JSON
ob_start();

require_once 'config/database.php';

// Clear any output buffer and set JSON header
ob_end_clean();
header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$courseId) {
    echo json_encode(['success' => false, 'message' => 'Course ID required']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    $query = "
        SELECT b.batch_id, b.batch_code, b.batch_name, b.start_date, b.end_date,
               b.schedule_details, b.classroom, b.status, 
               u.full_name as teacher_name
        FROM batches b
        LEFT JOIN users u ON b.teacher_id = u.user_id
        WHERE b.course_id = ?
        ORDER BY b.start_date
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$courseId]);
    $batches = $stmt->fetchAll();
    
    // Format dates for JSON
    foreach ($batches as &$batch) {
        if ($batch['start_date']) {
            $batch['start_date'] = date('Y-m-d', strtotime($batch['start_date']));
        }
        if ($batch['end_date']) {
            $batch['end_date'] = date('Y-m-d', strtotime($batch['end_date']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'batches' => $batches
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>