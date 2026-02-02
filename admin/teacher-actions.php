<?php
/**
 * Teacher Actions AJAX Handler
 * Handles all AJAX requests for teacher management
 * File: teacher-actions.php
 */

// Enable error reporting for debugging (disable in production)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set headers for AJAX response
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start session and include dependencies
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/../config/notifications.php';

// Check if request is AJAX
if (!isAjaxRequest()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Direct access not allowed',
        'redirect' => 'login.php'
    ]);
    exit;
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'errors' => [],
    'redirect' => null,
    'timestamp' => time()
];

try {
    // Get database connection
    $db = (new Database())->getConnection();
    
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        throw new Exception('Authentication required', 401);
    }
    
    // Get action from request
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $teacherId = intval($_POST['teacher_id'] ?? $_GET['id'] ?? 0);
    $bulkIds = $_POST['teacher_ids'] ?? [];
    
    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrfToken)) {
            throw new Exception('Invalid security token', 403);
        }
    }
    
    // Check permissions
    if (!hasPermission('manage_teachers')) {
        throw new Exception('Insufficient permissions', 403);
    }
    
    // Rate limiting for actions
    applyRateLimit('teacher_actions_' . $_SESSION['user_id'], 60, 30);
    
    // Process action
    switch ($action) {
        case 'get_details':
            handleGetDetails($db, $teacherId);
            break;
            
        case 'activate':
            handleActivateTeacher($db, $teacherId);
            break;
            
        case 'deactivate':
            handleDeactivateTeacher($db, $teacherId);
            break;
            
        case 'suspend':
            handleSuspendTeacher($db, $teacherId);
            break;
            
        case 'bulk_activate':
            handleBulkActivate($db, $bulkIds);
            break;
            
        case 'bulk_deactivate':
            handleBulkDeactivate($db, $bulkIds);
            break;
            
        case 'bulk_delete':
            handleBulkDelete($db, $bulkIds);
            break;
            
        case 'update_profile':
            handleUpdateProfile($db, $teacherId);
            break;
            
        case 'upload_photo':
            handleUploadPhoto($db, $teacherId);
            break;
            
        case 'assign_batch':
            handleAssignBatch($db, $teacherId);
            break;
            
        case 'remove_batch':
            handleRemoveBatch($db, $teacherId);
            break;
            
        case 'get_schedule':
            handleGetSchedule($db, $teacherId);
            break;
            
        case 'update_schedule':
            handleUpdateSchedule($db, $teacherId);
            break;
            
        case 'get_feedback':
            handleGetFeedback($db, $teacherId);
            break;
            
        case 'add_feedback':
            handleAddFeedback($db, $teacherId);
            break;
            
        case 'send_message':
            handleSendMessage($db, $teacherId);
            break;
            
        case 'generate_report':
            handleGenerateReport($db, $teacherId);
            break;
            
        case 'get_statistics':
            handleGetStatistics($db, $teacherId);
            break;
            
        case 'export_data':
            handleExportData($db, $teacherId);
            break;
            
        case 'quick_edit':
            handleQuickEdit($db, $teacherId);
            break;
            
        case 'check_availability':
            handleCheckAvailability($db, $teacherId);
            break;
            
        case 'get_calendar':
            handleGetCalendar($db, $teacherId);
            break;
            
        case 'toggle_feature':
            handleToggleFeature($db, $teacherId);
            break;
            
        case 'get_notifications':
            handleGetNotifications($db, $teacherId);
            break;
            
        case 'mark_notification_read':
            handleMarkNotificationRead($db, $teacherId);
            break;
            
        case 'search_teachers':
            handleSearchTeachers($db);
            break;
            
        case 'get_similar_teachers':
            handleGetSimilarTeachers($db, $teacherId);
            break;
            
        case 'merge_teachers':
            handleMergeTeachers($db);
            break;
            
        case 'duplicate_teacher':
            handleDuplicateTeacher($db, $teacherId);
            break;
            
        case 'archive_teacher':
            handleArchiveTeacher($db, $teacherId);
            break;
            
        case 'restore_teacher':
            handleRestoreTeacher($db, $teacherId);
            break;
            
        case 'get_activity_log':
            handleGetActivityLog($db, $teacherId);
            break;
            
        case 'validate_data':
            handleValidateData($db);
            break;
            
        default:
            throw new Exception('Invalid action specified', 400);
    }
    
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['error_code'] = $statusCode;
    
    // Log error
    logSecurityEvent('teacher_action_error', [
        'action' => $action ?? 'unknown',
        'error' => $e->getMessage(),
        'trace' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getTraceAsString() : null,
        'user_id' => $_SESSION['user_id'] ?? null,
        'teacher_id' => $teacherId ?? null
    ]);
    
    echo json_encode($response);
    exit;
}

/**
 * Get teacher details
 */
function handleGetDetails($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $query = "
        SELECT 
            u.*,
            up.*,
            u.user_id as id,
            DATE_FORMAT(u.created_at, '%Y-%m-%d') as join_date,
            DATE_FORMAT(u.last_login, '%Y-%m-%d %H:%i:%s') as last_login_formatted,
            TIMESTAMPDIFF(YEAR, up.date_of_birth, CURDATE()) as age,
            
            -- Statistics
            (SELECT COUNT(*) FROM batches WHERE teacher_id = u.user_id AND status = 'ongoing') as active_batches,
            (SELECT COUNT(*) FROM batches WHERE teacher_id = u.user_id AND status = 'completed') as completed_batches,
            (SELECT COUNT(DISTINCT course_id) FROM batches WHERE teacher_id = u.user_id) as courses_taught,
            
            -- Student statistics
            (SELECT COUNT(*) FROM enrollments e 
             JOIN batches b ON e.batch_id = b.batch_id 
             WHERE b.teacher_id = u.user_id AND e.enrollment_status = 'active') as active_students,
            (SELECT COUNT(*) FROM enrollments e 
             JOIN batches b ON e.batch_id = b.batch_id 
             WHERE b.teacher_id = u.user_id) as total_students,
            
            -- Feedback statistics
            (SELECT AVG(rating) FROM teacher_feedback WHERE teacher_id = u.user_id) as avg_rating,
            (SELECT COUNT(*) FROM teacher_feedback WHERE teacher_id = u.user_id) as feedback_count,
            (SELECT COUNT(*) FROM teacher_feedback WHERE teacher_id = u.user_id AND rating >= 4) as positive_feedback,
            
            -- Current courses
            (SELECT GROUP_CONCAT(CONCAT(c.course_code, ' - ', c.course_name) SEPARATOR '|') 
             FROM batches b 
             JOIN courses c ON b.course_id = c.course_id 
             WHERE b.teacher_id = u.user_id AND b.status = 'ongoing' 
             LIMIT 5) as current_courses,
            
            -- Upcoming classes (next 7 days)
            (SELECT COUNT(*) FROM class_sessions cs
             JOIN batches b ON cs.batch_id = b.batch_id
             WHERE b.teacher_id = u.user_id 
             AND cs.session_date >= CURDATE() 
             AND cs.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             AND cs.status = 'scheduled') as upcoming_classes,
            
            -- Pending tasks
            (SELECT COUNT(*) FROM teacher_tasks 
             WHERE teacher_id = u.user_id AND status = 'pending') as pending_tasks
            
        FROM users u 
        LEFT JOIN user_profiles up ON u.user_id = up.user_id 
        WHERE u.user_id = ? AND u.user_type = 'teacher'
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        throw new Exception('Teacher not found', 404);
    }
    
    // Get additional data
    $teacher['batches'] = getTeacherBatches($db, $teacherId);
    $teacher['schedule'] = getTeacherSchedule($db, $teacherId);
    $teacher['recent_feedback'] = getRecentFeedback($db, $teacherId);
    $teacher['documents'] = getTeacherDocuments($db, $teacherId);
    $teacher['availability'] = getTeacherAvailability($db, $teacherId);
    
    // Format data
    $teacher['full_name'] = htmlspecialchars($teacher['full_name']);
    $teacher['email'] = htmlspecialchars($teacher['email']);
    $teacher['qualifications'] = nl2br(htmlspecialchars($teacher['qualifications'] ?? ''));
    $teacher['experience_summary'] = formatExperience($teacher['years_experience']);
    $teacher['status_badge'] = getStatusBadge($teacher['account_status']);
    
    // Calculate performance metrics
    $teacher['performance_score'] = calculatePerformanceScore($teacher);
    
    $response = [
        'success' => true,
        'message' => 'Teacher details retrieved',
        'data' => $teacher,
        'html' => generateTeacherDetailsHTML($teacher)
    ];
    
    echo json_encode($response);
}

/**
 * Activate teacher
 */
function handleActivateTeacher($db, $teacherId) {
    validateTeacherId($teacherId);
    
    // Check if teacher exists and is not already active
    $checkStmt = $db->prepare("SELECT account_status FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $checkStmt->execute([$teacherId]);
    $teacher = $checkStmt->fetch();
    
    if (!$teacher) {
        throw new Exception('Teacher not found', 404);
    }
    
    if ($teacher['account_status'] === 'active') {
        throw new Exception('Teacher is already active', 400);
    }
    
    // Update status
    $stmt = $db->prepare("
        UPDATE users 
        SET account_status = 'active', 
            updated_at = NOW(),
            activated_by = ?,
            activated_at = NOW()
        WHERE user_id = ? AND user_type = 'teacher'
    ");
    
    $success = $stmt->execute([$_SESSION['user_id'], $teacherId]);
    
    if ($success) {
        // Log activity
        logTeacherActivity($db, $teacherId, 'teacher_activated', [
            'activated_by' => $_SESSION['user_id'],
            'previous_status' => $teacher['account_status']
        ]);
        
        // Send notification to teacher
        sendTeacherNotification($db, $teacherId, 'account_activated', [
            'activated_by' => $_SESSION['full_name'] ?? 'Administrator'
        ]);
        
        // Send email notification
        sendTeacherEmail($teacherId, 'account_activated');
        
        $response = [
            'success' => true,
            'message' => 'Teacher activated successfully',
            'data' => [
                'teacher_id' => $teacherId,
                'new_status' => 'active',
                'status_badge' => getStatusBadge('active')
            ]
        ];
    } else {
        throw new Exception('Failed to activate teacher', 500);
    }
    
    echo json_encode($response);
}

/**
 * Deactivate teacher
 */
function handleDeactivateTeacher($db, $teacherId) {
    validateTeacherId($teacherId);
    
    // Check if teacher exists and is not already inactive
    $checkStmt = $db->prepare("
        SELECT u.account_status, u.full_name, u.email,
               (SELECT COUNT(*) FROM batches WHERE teacher_id = ? AND status = 'ongoing') as active_batches
        FROM users u 
        WHERE u.user_id = ? AND u.user_type = 'teacher'
    ");
    $checkStmt->execute([$teacherId, $teacherId]);
    $teacher = $checkStmt->fetch();
    
    if (!$teacher) {
        throw new Exception('Teacher not found', 404);
    }
    
    if ($teacher['account_status'] === 'inactive') {
        throw new Exception('Teacher is already inactive', 400);
    }
    
    // Check if teacher has active batches
    if ($teacher['active_batches'] > 0) {
        throw new Exception('Cannot deactivate teacher with active batches. Reassign batches first.', 400);
    }
    
    // Update status
    $stmt = $db->prepare("
        UPDATE users 
        SET account_status = 'inactive', 
            updated_at = NOW(),
            deactivated_by = ?,
            deactivated_at = NOW()
        WHERE user_id = ? AND user_type = 'teacher'
    ");
    
    $success = $stmt->execute([$_SESSION['user_id'], $teacherId]);
    
    if ($success) {
        // Log activity
        logTeacherActivity($db, $teacherId, 'teacher_deactivated', [
            'deactivated_by' => $_SESSION['user_id'],
            'previous_status' => $teacher['account_status']
        ]);
        
        // Send notification to teacher
        sendTeacherNotification($db, $teacherId, 'account_deactivated', [
            'deactivated_by' => $_SESSION['full_name'] ?? 'Administrator',
            'reason' => $_POST['reason'] ?? 'Administrative action'
        ]);
        
        $response = [
            'success' => true,
            'message' => 'Teacher deactivated successfully',
            'data' => [
                'teacher_id' => $teacherId,
                'new_status' => 'inactive',
                'status_badge' => getStatusBadge('inactive')
            ]
        ];
    } else {
        throw new Exception('Failed to deactivate teacher', 500);
    }
    
    echo json_encode($response);
}

/**
 * Suspend teacher
 */
function handleSuspendTeacher($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $reason = cleanInput($_POST['reason'] ?? 'Violation of terms');
    $duration = intval($_POST['duration'] ?? 7); // days
    $endDate = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
    
    // Update status
    $stmt = $db->prepare("
        UPDATE users 
        SET account_status = 'suspended', 
            updated_at = NOW(),
            suspension_reason = ?,
            suspension_start = NOW(),
            suspension_end = ?,
            suspended_by = ?
        WHERE user_id = ? AND user_type = 'teacher'
    ");
    
    $success = $stmt->execute([$reason, $endDate, $_SESSION['user_id'], $teacherId]);
    
    if ($success) {
        // Log activity
        logTeacherActivity($db, $teacherId, 'teacher_suspended', [
            'suspended_by' => $_SESSION['user_id'],
            'reason' => $reason,
            'duration' => $duration,
            'end_date' => $endDate
        ]);
        
        // Send notification
        sendTeacherNotification($db, $teacherId, 'account_suspended', [
            'reason' => $reason,
            'duration' => $duration,
            'end_date' => $endDate,
            'suspended_by' => $_SESSION['full_name'] ?? 'Administrator'
        ]);
        
        $response = [
            'success' => true,
            'message' => 'Teacher suspended successfully',
            'data' => [
                'teacher_id' => $teacherId,
                'new_status' => 'suspended',
                'status_badge' => getStatusBadge('suspended'),
                'suspension_end' => $endDate
            ]
        ];
    } else {
        throw new Exception('Failed to suspend teacher', 500);
    }
    
    echo json_encode($response);
}

/**
 * Bulk activate teachers
 */
function handleBulkActivate($db, $teacherIds) {
    validateBulkIds($teacherIds);
    
    $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
    
    // Get current statuses for logging
    $checkStmt = $db->prepare("
        SELECT user_id, account_status 
        FROM users 
        WHERE user_id IN ($placeholders) AND user_type = 'teacher'
    ");
    $checkStmt->execute($teacherIds);
    $teachers = $checkStmt->fetchAll();
    
    if (empty($teachers)) {
        throw new Exception('No valid teachers found', 404);
    }
    
    // Update status
    $stmt = $db->prepare("
        UPDATE users 
        SET account_status = 'active', 
            updated_at = NOW(),
            activated_by = ?,
            activated_at = NOW()
        WHERE user_id IN ($placeholders) AND user_type = 'teacher'
    ");
    
    $params = array_merge([$_SESSION['user_id']], $teacherIds);
    $success = $stmt->execute($params);
    
    if ($success) {
        // Log activities
        foreach ($teachers as $teacher) {
            logTeacherActivity($db, $teacher['user_id'], 'teacher_activated_bulk', [
                'activated_by' => $_SESSION['user_id'],
                'previous_status' => $teacher['account_status']
            ]);
            
            sendTeacherNotification($db, $teacher['user_id'], 'account_activated', [
                'activated_by' => $_SESSION['full_name'] ?? 'Administrator'
            ]);
        }
        
        $response = [
            'success' => true,
            'message' => count($teachers) . ' teachers activated successfully',
            'data' => [
                'count' => count($teachers),
                'teacher_ids' => $teacherIds
            ]
        ];
    } else {
        throw new Exception('Failed to activate teachers', 500);
    }
    
    echo json_encode($response);
}

/**
 * Bulk deactivate teachers
 */
function handleBulkDeactivate($db, $teacherIds) {
    validateBulkIds($teacherIds);
    
    $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
    
    // Check for active batches
    $checkStmt = $db->prepare("
        SELECT u.user_id, u.full_name,
               (SELECT COUNT(*) FROM batches WHERE teacher_id = u.user_id AND status = 'ongoing') as active_batches
        FROM users u 
        WHERE u.user_id IN ($placeholders) AND u.user_type = 'teacher'
    ");
    $checkStmt->execute($teacherIds);
    $teachers = $checkStmt->fetchAll();
    
    $teachersWithBatches = array_filter($teachers, fn($t) => $t['active_batches'] > 0);
    
    if (!empty($teachersWithBatches)) {
        $names = array_column($teachersWithBatches, 'full_name');
        throw new Exception('Cannot deactivate teachers with active batches: ' . implode(', ', $names), 400);
    }
    
    // Update status
    $stmt = $db->prepare("
        UPDATE users 
        SET account_status = 'inactive', 
            updated_at = NOW(),
            deactivated_by = ?,
            deactivated_at = NOW()
        WHERE user_id IN ($placeholders) AND user_type = 'teacher'
    ");
    
    $params = array_merge([$_SESSION['user_id']], $teacherIds);
    $success = $stmt->execute($params);
    
    if ($success) {
        // Log activities
        foreach ($teachers as $teacher) {
            logTeacherActivity($db, $teacher['user_id'], 'teacher_deactivated_bulk', [
                'deactivated_by' => $_SESSION['user_id']
            ]);
            
            sendTeacherNotification($db, $teacher['user_id'], 'account_deactivated', [
                'deactivated_by' => $_SESSION['full_name'] ?? 'Administrator'
            ]);
        }
        
        $response = [
            'success' => true,
            'message' => count($teachers) . ' teachers deactivated successfully',
            'data' => [
                'count' => count($teachers),
                'teacher_ids' => $teacherIds
            ]
        ];
    } else {
        throw new Exception('Failed to deactivate teachers', 500);
    }
    
    echo json_encode($response);
}

/**
 * Bulk delete teachers (soft delete)
 */
function handleBulkDelete($db, $teacherIds) {
    validateBulkIds($teacherIds);
    
    $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
    
    // Check if teachers can be deleted
    $checkStmt = $db->prepare("
        SELECT u.user_id, u.full_name,
               (SELECT COUNT(*) FROM batches WHERE teacher_id = u.user_id) as total_batches,
               (SELECT COUNT(*) FROM teacher_feedback WHERE teacher_id = u.user_id) as feedback_count
        FROM users u 
        WHERE u.user_id IN ($placeholders) AND u.user_type = 'teacher'
    ");
    $checkStmt->execute($teacherIds);
    $teachers = $checkStmt->fetchAll();
    
    // Soft delete (mark as deleted)
    $stmt = $db->prepare("
        UPDATE users 
        SET deleted_at = NOW(),
            deleted_by = ?,
            updated_at = NOW()
        WHERE user_id IN ($placeholders) AND user_type = 'teacher'
    ");
    
    $params = array_merge([$_SESSION['user_id']], $teacherIds);
    $success = $stmt->execute($params);
    
    if ($success) {
        // Log activities
        foreach ($teachers as $teacher) {
            logTeacherActivity($db, $teacher['user_id'], 'teacher_deleted_bulk', [
                'deleted_by' => $_SESSION['user_id'],
                'batches_affected' => $teacher['total_batches'],
                'feedback_affected' => $teacher['feedback_count']
            ]);
        }
        
        // Archive related data
        archiveTeacherData($db, $teacherIds);
        
        $response = [
            'success' => true,
            'message' => count($teachers) . ' teachers deleted successfully',
            'data' => [
                'count' => count($teachers),
                'teacher_ids' => $teacherIds,
                'archived' => true
            ]
        ];
    } else {
        throw new Exception('Failed to delete teachers', 500);
    }
    
    echo json_encode($response);
}

/**
 * Update teacher profile
 */
function handleUpdateProfile($db, $teacherId) {
    validateTeacherId($teacherId);
    
    // Validation rules
    $rules = [
        'full_name' => ['type' => 'string', 'required' => true, 'min_length' => 2, 'max_length' => 100],
        'email' => ['type' => 'email', 'required' => true],
        'phone' => ['type' => 'phone', 'required' => true],
        'cnic' => ['type' => 'cnic', 'required' => true],
        'gender' => ['type' => 'string', 'required' => true],
        'date_of_birth' => ['type' => 'date', 'required' => true],
        'education_level' => ['type' => 'string', 'required' => true],
        'qualifications' => ['type' => 'string', 'required' => true, 'max_length' => 500],
        'specialization' => ['type' => 'string', 'max_length' => 200],
        'years_experience' => ['type' => 'int', 'min' => 0, 'max' => 50],
        'address' => ['type' => 'string', 'max_length' => 500],
        'bio' => ['type' => 'string', 'max_length' => 1000]
    ];
    
    $validation = validateInput($_POST, $rules);
    
    if (!$validation['success']) {
        throw new Exception('Validation failed: ' . json_encode($validation['errors']), 400);
    }
    
    $data = $validation['data'];
    
    // Check email uniqueness (excluding current teacher)
    $emailCheck = $db->prepare("
        SELECT user_id FROM users 
        WHERE email = ? AND user_id != ? AND deleted_at IS NULL
    ");
    $emailCheck->execute([$data['email'], $teacherId]);
    
    if ($emailCheck->fetch()) {
        throw new Exception('Email already exists', 400);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update users table
        $userStmt = $db->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, phone = ?, cnic = ?, updated_at = NOW()
            WHERE user_id = ? AND user_type = 'teacher'
        ");
        
        $userStmt->execute([
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['cnic'],
            $teacherId
        ]);
        
        // Update or insert user_profiles
        $profileCheck = $db->prepare("SELECT user_id FROM user_profiles WHERE user_id = ?");
        $profileCheck->execute([$teacherId]);
        
        if ($profileCheck->fetch()) {
            $profileStmt = $db->prepare("
                UPDATE user_profiles 
                SET gender = ?, date_of_birth = ?, education_level = ?, 
                    qualifications = ?, specialization = ?, years_experience = ?,
                    address = ?, bio = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $profileStmt->execute([
                $data['gender'],
                $data['date_of_birth'],
                $data['education_level'],
                $data['qualifications'],
                $data['specialization'] ?? null,
                $data['years_experience'] ?? 0,
                $data['address'] ?? null,
                $data['bio'] ?? null,
                $teacherId
            ]);
        } else {
            $profileStmt = $db->prepare("
                INSERT INTO user_profiles 
                (user_id, gender, date_of_birth, education_level, qualifications, 
                 specialization, years_experience, address, bio, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $profileStmt->execute([
                $teacherId,
                $data['gender'],
                $data['date_of_birth'],
                $data['education_level'],
                $data['qualifications'],
                $data['specialization'] ?? null,
                $data['years_experience'] ?? 0,
                $data['address'] ?? null,
                $data['bio'] ?? null
            ]);
        }
        
        // Log activity
        logTeacherActivity($db, $teacherId, 'profile_updated', [
            'updated_by' => $_SESSION['user_id'],
            'fields_updated' => array_keys($data)
        ]);
        
        $db->commit();
        
        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'teacher_id' => $teacherId,
                'updated_fields' => array_keys($data)
            ]
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('Failed to update profile: ' . $e->getMessage(), 500);
    }
    
    echo json_encode($response);
}

/**
 * Upload teacher photo
 */
function handleUploadPhoto($db, $teacherId) {
    validateTeacherId($teacherId);
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error', 400);
    }
    
    $file = $_FILES['photo'];
    
    // Validate file
    $validation = validateFileUpload($file, [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ], 5242880); // 5MB
    
    if (!$validation['success']) {
        throw new Exception('File validation failed: ' . implode(', ', $validation['errors']), 400);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'teacher_' . $teacherId . '_' . time() . '.' . $extension;
    $uploadDir = __DIR__ . '/../uploads/teachers/';
    
    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save file', 500);
    }
    
    // Create thumbnail
    createThumbnail($uploadPath, $uploadDir . 'thumb_' . $filename, 150, 150);
    
    // Update database
    $stmt = $db->prepare("
        UPDATE user_profiles 
        SET profile_picture = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    
    $stmt->execute([$filename, $teacherId]);
    
    // Log activity
    logTeacherActivity($db, $teacherId, 'photo_uploaded', [
        'uploaded_by' => $_SESSION['user_id'],
        'filename' => $filename,
        'size' => $file['size']
    ]);
    
    $response = [
        'success' => true,
        'message' => 'Photo uploaded successfully',
        'data' => [
            'teacher_id' => $teacherId,
            'filename' => $filename,
            'url' => '/uploads/teachers/' . $filename,
            'thumbnail_url' => '/uploads/teachers/thumb_' . $filename
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Assign batch to teacher
 */
function handleAssignBatch($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $batchId = intval($_POST['batch_id']);
    $assignmentDate = $_POST['assignment_date'] ?? date('Y-m-d');
    
    if ($batchId <= 0) {
        throw new Exception('Invalid batch ID', 400);
    }
    
    // Check if batch exists
    $batchCheck = $db->prepare("
        SELECT batch_id, batch_name, course_id, teacher_id 
        FROM batches 
        WHERE batch_id = ? AND status = 'upcoming'
    ");
    $batchCheck->execute([$batchId]);
    $batch = $batchCheck->fetch();
    
    if (!$batch) {
        throw new Exception('Batch not found or not available for assignment', 404);
    }
    
    // Check if batch already has a teacher
    if ($batch['teacher_id'] && $batch['teacher_id'] != $teacherId) {
        throw new Exception('Batch is already assigned to another teacher', 400);
    }
    
    // Check teacher availability for batch schedule
    if (!isTeacherAvailable($db, $teacherId, $batchId)) {
        throw new Exception('Teacher is not available for this batch schedule', 400);
    }
    
    // Update batch
    $stmt = $db->prepare("
        UPDATE batches 
        SET teacher_id = ?, updated_at = NOW()
        WHERE batch_id = ?
    ");
    
    $success = $stmt->execute([$teacherId, $batchId]);
    
    if ($success) {
        // Create assignment record
        $assignmentStmt = $db->prepare("
            INSERT INTO teacher_batch_assignments 
            (teacher_id, batch_id, assigned_by, assignment_date, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $assignmentStmt->execute([
            $teacherId,
            $batchId,
            $_SESSION['user_id'],
            $assignmentDate
        ]);
        
        // Log activity
        logTeacherActivity($db, $teacherId, 'batch_assigned', [
            'batch_id' => $batchId,
            'batch_name' => $batch['batch_name'],
            'assigned_by' => $_SESSION['user_id']
        ]);
        
        // Send notification
        sendTeacherNotification($db, $teacherId, 'batch_assigned', [
            'batch_name' => $batch['batch_name'],
            'assigned_by' => $_SESSION['full_name'] ?? 'Administrator'
        ]);
        
        $response = [
            'success' => true,
            'message' => 'Batch assigned successfully',
            'data' => [
                'teacher_id' => $teacherId,
                'batch_id' => $batchId,
                'batch_name' => $batch['batch_name'],
                'assignment_id' => $db->lastInsertId()
            ]
        ];
    } else {
        throw new Exception('Failed to assign batch', 500);
    }
    
    echo json_encode($response);
}

/**
 * Get teacher schedule
 */
function handleGetSchedule($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $startDate = $_GET['start'] ?? date('Y-m-d');
    $endDate = $_GET['end'] ?? date('Y-m-d', strtotime('+30 days'));
    
    $query = "
        SELECT 
            cs.session_id,
            cs.session_date,
            cs.start_time,
            cs.end_time,
            cs.topic,
            cs.status,
            b.batch_name,
            b.batch_code,
            c.course_name,
            c.course_code,
            r.room_number,
            r.room_name
        FROM class_sessions cs
        JOIN batches b ON cs.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN rooms r ON cs.room_id = r.room_id
        WHERE b.teacher_id = ?
        AND cs.session_date BETWEEN ? AND ?
        ORDER BY cs.session_date, cs.start_time
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$teacherId, $startDate, $endDate]);
    $sessions = $stmt->fetchAll();
    
    // Format for calendar
    $calendarEvents = [];
    foreach ($sessions as $session) {
        $calendarEvents[] = [
            'id' => $session['session_id'],
            'title' => $session['course_code'] . ' - ' . $session['topic'],
            'start' => $session['session_date'] . 'T' . $session['start_time'],
            'end' => $session['session_date'] . 'T' . $session['end_time'],
            'extendedProps' => [
                'batch' => $session['batch_name'],
                'course' => $session['course_name'],
                'room' => $session['room_name'] ?? 'N/A',
                'status' => $session['status']
            ],
            'className' => 'session-status-' . $session['status']
        ];
    }
    
    $response = [
        'success' => true,
        'message' => 'Schedule retrieved',
        'data' => [
            'sessions' => $sessions,
            'calendar_events' => $calendarEvents,
            'date_range' => ['start' => $startDate, 'end' => $endDate]
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Get teacher feedback
 */
function handleGetFeedback($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $query = "
        SELECT 
            tf.feedback_id,
            tf.rating,
            tf.comment,
            tf.created_at,
            s.full_name as student_name,
            s.email as student_email,
            c.course_name,
            b.batch_name,
            ROW_NUMBER() OVER (ORDER BY tf.created_at DESC) as row_num
        FROM teacher_feedback tf
        JOIN users s ON tf.student_id = s.user_id
        JOIN batches b ON tf.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        WHERE tf.teacher_id = ?
        ORDER BY tf.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$teacherId, $limit, $offset]);
    $feedback = $stmt->fetchAll();
    
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM teacher_feedback WHERE teacher_id = ?");
    $countStmt->execute([$teacherId]);
    $total = $countStmt->fetchColumn();
    
    // Calculate statistics
    $statsStmt = $db->prepare("
        SELECT 
            AVG(rating) as avg_rating,
            COUNT(*) as total_feedback,
            SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_feedback,
            SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative_feedback
        FROM teacher_feedback 
        WHERE teacher_id = ?
    ");
    $statsStmt->execute([$teacherId]);
    $stats = $statsStmt->fetch();
    
    $response = [
        'success' => true,
        'message' => 'Feedback retrieved',
        'data' => [
            'feedback' => $feedback,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'statistics' => $stats
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Send message to teacher
 */
function handleSendMessage($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $subject = cleanInput($_POST['subject'] ?? 'Message from Administration');
    $message = cleanInput($_POST['message'] ?? '', 'string');
    $priority = in_array($_POST['priority'] ?? 'normal', ['low', 'normal', 'high', 'urgent']) 
                ? $_POST['priority'] 
                : 'normal';
    
    if (empty($message)) {
        throw new Exception('Message cannot be empty', 400);
    }
    
    // Get teacher details
    $teacherStmt = $db->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
    $teacherStmt->execute([$teacherId]);
    $teacher = $teacherStmt->fetch();
    
    if (!$teacher) {
        throw new Exception('Teacher not found', 404);
    }
    
    // Save message to database
    $stmt = $db->prepare("
        INSERT INTO messages 
        (sender_id, receiver_id, subject, message, priority, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $teacherId,
        $subject,
        $message,
        $priority
    ]);
    
    $messageId = $db->lastInsertId();
    
    // Send email notification
    $emailSent = sendEmailNotification($teacher['email'], $subject, $message);
    
    // Send in-app notification
    sendTeacherNotification($db, $teacherId, 'new_message', [
        'subject' => $subject,
        'sender' => $_SESSION['full_name'] ?? 'Administrator',
        'priority' => $priority
    ]);
    
    // Log activity
    logTeacherActivity($db, $teacherId, 'message_sent', [
        'sender_id' => $_SESSION['user_id'],
        'message_id' => $messageId,
        'priority' => $priority,
        'email_sent' => $emailSent
    ]);
    
    $response = [
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => [
            'message_id' => $messageId,
            'teacher_id' => $teacherId,
            'email_sent' => $emailSent,
            'priority' => $priority
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Generate teacher report
 */
function handleGenerateReport($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $reportType = $_POST['report_type'] ?? 'performance';
    $startDate = $_POST['start_date'] ?? date('Y-m-01');
    $endDate = $_POST['end_date'] ?? date('Y-m-t');
    
    // Get teacher details
    $teacherStmt = $db->prepare("
        SELECT u.*, up.* 
        FROM users u 
        LEFT JOIN user_profiles up ON u.user_id = up.user_id 
        WHERE u.user_id = ? AND u.user_type = 'teacher'
    ");
    $teacherStmt->execute([$teacherId]);
    $teacher = $teacherStmt->fetch();
    
    if (!$teacher) {
        throw new Exception('Teacher not found', 404);
    }
    
    $reportData = [];
    
    switch ($reportType) {
        case 'performance':
            $reportData = generatePerformanceReport($db, $teacherId, $startDate, $endDate);
            break;
            
        case 'attendance':
            $reportData = generateAttendanceReport($db, $teacherId, $startDate, $endDate);
            break;
            
        case 'financial':
            $reportData = generateFinancialReport($db, $teacherId, $startDate, $endDate);
            break;
            
        case 'comprehensive':
            $reportData = generateComprehensiveReport($db, $teacherId, $startDate, $endDate);
            break;
            
        default:
            throw new Exception('Invalid report type', 400);
    }
    
    // Generate PDF report
    $pdfPath = generatePDFReport($teacher, $reportData, $reportType, $startDate, $endDate);
    
    // Log report generation
    logTeacherActivity($db, $teacherId, 'report_generated', [
        'report_type' => $reportType,
        'generated_by' => $_SESSION['user_id'],
        'date_range' => ['start' => $startDate, 'end' => $endDate],
        'pdf_path' => $pdfPath
    ]);
    
    $response = [
        'success' => true,
        'message' => 'Report generated successfully',
        'data' => [
            'report_type' => $reportType,
            'teacher_id' => $teacherId,
            'date_range' => ['start' => $startDate, 'end' => $endDate],
            'download_url' => $pdfPath,
            'report_data' => $reportData
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Search teachers
 */
function handleSearchTeachers($db) {
    $searchTerm = cleanInput($_GET['q'] ?? '');
    $limit = intval($_GET['limit'] ?? 10);
    
    if (strlen($searchTerm) < 2) {
        throw new Exception('Search term must be at least 2 characters', 400);
    }
    
    $searchPattern = "%{$searchTerm}%";
    
    $query = "
        SELECT 
            u.user_id,
            u.full_name,
            u.email,
            u.phone,
            u.account_status,
            up.years_experience,
            up.education_level,
            up.specialization
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE u.user_type = 'teacher'
        AND u.deleted_at IS NULL
        AND (
            u.full_name LIKE ? OR
            u.email LIKE ? OR
            u.phone LIKE ? OR
            up.education_level LIKE ? OR
            up.specialization LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN u.full_name LIKE ? THEN 1
                WHEN u.email LIKE ? THEN 2
                ELSE 3
            END,
            u.full_name
        LIMIT ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute(array_fill(0, 7, $searchPattern) + [7 => $limit]);
    $teachers = $stmt->fetchAll();
    
    // Format results
    $results = array_map(function($teacher) {
        return [
            'id' => $teacher['user_id'],
            'text' => $teacher['full_name'] . ' (' . $teacher['email'] . ')',
            'name' => $teacher['full_name'],
            'email' => $teacher['email'],
            'phone' => $teacher['phone'],
            'status' => $teacher['account_status'],
            'experience' => $teacher['years_experience'] . ' years',
            'specialization' => $teacher['specialization']
        ];
    }, $teachers);
    
    $response = [
        'success' => true,
        'message' => 'Search completed',
        'data' => [
            'results' => $results,
            'count' => count($results),
            'search_term' => $searchTerm
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Quick edit teacher
 */
function handleQuickEdit($db, $teacherId) {
    validateTeacherId($teacherId);
    
    $field = cleanInput($_POST['field'] ?? '');
    $value = cleanInput($_POST['value'] ?? '');
    
    $allowedFields = [
        'full_name' => 'string',
        'email' => 'email',
        'phone' => 'phone',
        'account_status' => 'status',
        'years_experience' => 'int'
    ];
    
    if (!isset($allowedFields[$field])) {
        throw new Exception('Invalid field for quick edit', 400);
    }
    
    // Validate based on field type
    switch ($allowedFields[$field]) {
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format', 400);
            }
            break;
            
        case 'phone':
            if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $value)) {
                throw new Exception('Invalid phone number', 400);
            }
            break;
            
        case 'status':
            $allowedStatuses = ['active', 'inactive', 'suspended', 'pending_verification'];
            if (!in_array($value, $allowedStatuses)) {
                throw new Exception('Invalid status value', 400);
            }
            break;
            
        case 'int':
            if (!is_numeric($value) || $value < 0) {
                throw new Exception('Invalid number', 400);
            }
            break;
    }
    
    // Determine which table to update
    if (in_array($field, ['years_experience'])) {
        $table = 'user_profiles';
        $where = 'user_id = ?';
    } else {
        $table = 'users';
        $where = 'user_id = ? AND user_type = "teacher"';
    }
    
    // Update field
    $stmt = $db->prepare("UPDATE {$table} SET {$field} = ?, updated_at = NOW() WHERE {$where}");
    $success = $stmt->execute([$value, $teacherId]);
    
    if ($success) {
        // Log activity
        logTeacherActivity($db, $teacherId, 'quick_edit', [
            'field' => $field,
            'old_value' => $_POST['old_value'] ?? null,
            'new_value' => $value,
            'edited_by' => $_SESSION['user_id']
        ]);
        
        $response = [
            'success' => true,
            'message' => 'Field updated successfully',
            'data' => [
                'field' => $field,
                'value' => $value,
                'teacher_id' => $teacherId
            ]
        ];
    } else {
        throw new Exception('Failed to update field', 500);
    }
    
    echo json_encode($response);
}

/**
 * Helper functions
 */

/**
 * Validate teacher ID
 */
function validateTeacherId($teacherId) {
    if ($teacherId <= 0) {
        throw new Exception('Invalid teacher ID', 400);
    }
}

/**
 * Validate bulk IDs
 */
function validateBulkIds($teacherIds) {
    if (empty($teacherIds) || !is_array($teacherIds)) {
        throw new Exception('No teachers selected', 400);
    }
    
    $teacherIds = array_filter($teacherIds, 'is_numeric');
    $teacherIds = array_map('intval', $teacherIds);
    $teacherIds = array_filter($teacherIds, fn($id) => $id > 0);
    
    if (empty($teacherIds)) {
        throw new Exception('Invalid teacher IDs', 400);
    }
    
    return $teacherIds;
}

/**
 * Get teacher batches
 */
function getTeacherBatches($db, $teacherId) {
    $query = "
        SELECT 
            b.*,
            c.course_name,
            c.course_code,
            COUNT(DISTINCT e.enrollment_id) as student_count
        FROM batches b
        JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN enrollments e ON b.batch_id = e.batch_id AND e.enrollment_status = 'active'
        WHERE b.teacher_id = ?
        GROUP BY b.batch_id
        ORDER BY b.start_date DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$teacherId]);
    return $stmt->fetchAll();
}

/**
 * Log teacher activity
 */
function logTeacherActivity($db, $teacherId, $activityType, $details = []) {
    $stmt = $db->prepare("
        INSERT INTO teacher_activity_logs 
        (teacher_id, activity_type, activity_details, performed_by, performed_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $teacherId,
        $activityType,
        json_encode($details),
        $_SESSION['user_id']
    ]);
}

/**
 * Send teacher notification
 */
function sendTeacherNotification($db, $teacherId, $notificationType, $data = []) {
    $stmt = $db->prepare("
        INSERT INTO notifications 
        (user_id, notification_type, notification_data, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    
    $stmt->execute([
        $teacherId,
        $notificationType,
        json_encode($data)
    ]);
    
    return $db->lastInsertId();
}

/**
 * Send teacher email
 */
function sendTeacherEmail($teacherId, $template, $data = []) {
    // This would integrate with your email system
    // For now, just log the intention
    logSecurityEvent('teacher_email_queued', [
        'teacher_id' => $teacherId,
        'template' => $template,
        'data' => $data
    ]);
    
    return true;
}

/**
 * Generate teacher details HTML
 */
function generateTeacherDetailsHTML($teacher) {
    ob_start();
    ?>
    <div class="teacher-details">
        <div class="row">
            <div class="col-md-4">
                <div class="text-center mb-4">
                    <?php if (!empty($teacher['profile_picture'])): ?>
                        <img src="/uploads/teachers/<?php echo $teacher['profile_picture']; ?>" 
                             class="rounded-circle img-thumbnail" width="150" height="150">
                    <?php else: ?>
                        <div class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" 
                             style="width: 150px; height: 150px; font-size: 3rem;">
                            <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <h4 class="mt-3"><?php echo $teacher['full_name']; ?></h4>
                    <span class="badge bg-<?php echo getStatusColor($teacher['account_status']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $teacher['account_status'])); ?>
                    </span>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">Contact Information</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-envelope me-2 text-muted"></i> <?php echo $teacher['email']; ?></li>
                            <li><i class="fas fa-phone me-2 text-muted"></i> <?php echo formatPhone($teacher['phone']); ?></li>
                            <li><i class="fas fa-id-card me-2 text-muted"></i> <?php echo $teacher['cnic']; ?></li>
                            <li><i class="fas fa-birthday-cake me-2 text-muted"></i> 
                                <?php echo date('M d, Y', strtotime($teacher['date_of_birth'])); ?> 
                                (<?php echo $teacher['age']; ?> years)
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Professional Information</h6>
                                <p><strong>Education:</strong> <?php echo $teacher['education_level']; ?></p>
                                <p><strong>Experience:</strong> <?php echo $teacher['years_experience']; ?> years</p>
                                <p><strong>Specialization:</strong> <?php echo $teacher['specialization'] ?? 'N/A'; ?></p>
                                <p><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($teacher['join_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Performance Statistics</h6>
                                <div class="d-flex justify-content-around text-center">
                                    <div>
                                        <div class="h4 text-primary"><?php echo $teacher['active_batches']; ?></div>
                                        <small class="text-muted">Active Batches</small>
                                    </div>
                                    <div>
                                        <div class="h4 text-success"><?php echo $teacher['active_students']; ?></div>
                                        <small class="text-muted">Active Students</small>
                                    </div>
                                    <div>
                                        <div class="h4 text-warning"><?php echo number_format($teacher['avg_rating'], 1); ?></div>
                                        <small class="text-muted">Rating</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($teacher['qualifications'])): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">Qualifications</h6>
                        <p><?php echo $teacher['qualifications']; ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($teacher['bio'])): ?>
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Bio</h6>
                        <p><?php echo $teacher['bio']; ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get status color
 */
function getStatusColor($status) {
    return match($status) {
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        'pending_verification' => 'warning',
        default => 'secondary'
    };
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $color = getStatusColor($status);
    $label = ucfirst(str_replace('_', ' ', $status));
    
    return "<span class='badge bg-{$color}'>{$label}</span>";
}

/**
 * Format experience
 */
function formatExperience($years) {
    if ($years == 0) return 'No experience';
    if ($years == 1) return '1 year experience';
    return "{$years} years experience";
}

/**
 * Calculate performance score
 */
function calculatePerformanceScore($teacher) {
    $score = 0;
    
    // Rating component (40%)
    $score += min(40, ($teacher['avg_rating'] ?? 0) * 8);
    
    // Active batches component (30%)
    $score += min(30, ($teacher['active_batches'] ?? 0) * 10);
    
    // Student count component (20%)
    $score += min(20, ($teacher['active_students'] ?? 0) / 10);
    
    // Experience component (10%)
    $score += min(10, ($teacher['years_experience'] ?? 0) * 0.5);
    
    return min(100, $score);
}

// Additional action handlers would follow the same pattern...

/**
 * Handle missing action
 */
if (!isset($action) || empty($action)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No action specified',
        'code' => 'NO_ACTION'
    ]);
    exit;
}
?>