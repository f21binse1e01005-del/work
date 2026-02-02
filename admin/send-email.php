<?php
/**
 * Send Email Endpoint
 * File: admin/send-email.php
 */

require_once 'includes/header.php';
require_once 'includes/email.php';

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$isAjax) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not allowed');
}

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['application_id'])) {
    echo json_encode(['success' => false, 'message' => 'Application ID required']);
    exit();
}

try {
    $email = new EmailNotification($conn);
    
    // Send email based on type
    $result = false;
    $message = '';
    
    if (isset($data['type'])) {
        switch ($data['type']) {
            case 'application_status':
                $result = $email->sendApplicationStatus(
                    $data['application_id'],
                    $data['status'],
                    $data['notes'] ?? ''
                );
                $message = $result ? 'Status email sent successfully' : 'Failed to send status email';
                break;
                
            case 'credentials':
                $result = $email->sendCredentials(
                    $data['user_id'],
                    $data['username'],
                    $data['password']
                );
                $message = $result ? 'Credentials email sent successfully' : 'Failed to send credentials email';
                break;
                
            case 'custom':
                $result = $email->sendCustomEmail(
                    $data['to'],
                    $data['subject'],
                    $data['message'],
                    $data['cc'] ?? '',
                    $data['bcc'] ?? ''
                );
                $message = $result ? 'Custom email sent successfully' : 'Failed to send custom email';
                break;
                
            default:
                $message = 'Unknown email type';
        }
    } else {
        // Default: send application status update
        $result = $email->sendApplicationStatus(
            $data['application_id'],
            'under_review',
            'Your application is being reviewed by our admissions team.'
        );
        $message = $result ? 'Email sent successfully' : 'Failed to send email';
    }
    
    // Log activity
    if ($result) {
        $activityQuery = "INSERT INTO user_activity_logs (user_id, activity_type, activity_details) 
                         VALUES (?, 'email_sent', ?)";
        $activityDetails = json_encode([
            'application_id' => $data['application_id'],
            'type' => $data['type'] ?? 'application_status'
        ]);
        
        $stmt = $db->prepare($activityQuery);
        $stmt->bind_param("is", $_SESSION['user_id'], $activityDetails);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => $result,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log('Email error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage()
    ]);
}
?>