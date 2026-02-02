<?php
/**
 * Skills Way Vocational Institute - Enrollment Application Processor
 * File: application-processor.php
 * Description: Processes enrollment form submissions with validation and database entry
 * Security: Prepared statements, input sanitization, CSRF protection
 */

// Start session for CSRF token verification
session_start();

// Database configuration
require_once 'config/database.php';

// Set response header
header('Content-Type: application/json');

// Enable error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF Token Validation
function validateCSRFToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Input sanitization
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate Pakistan CNIC format
function validateCNIC($cnic) {
    // CNIC format: 12345-1234567-1
    $pattern = '/^[0-9]{5}-[0-9]{7}-[0-9]{1}$/';
    return preg_match($pattern, $cnic);
}

// Validate Pakistani phone number
function validatePhone($phone) {
    // Pakistani phone format: 03XX-XXXXXXX or 03XXXXXXXXX
    $pattern = '/^03[0-9]{2}-?[0-9]{7}$/';
    return preg_match($pattern, $phone);
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate date of birth (must be at least 16 years old)
function validateDOB($dob) {
    $minAge = 16;
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age >= $minAge;
}

// Main response array
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'application_id' => null
];

try {
    // Check if form was submitted via POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Please submit the form.');
    }

    // Validate CSRF token
    if (!validateCSRFToken()) {
        throw new Exception('Security token validation failed. Please refresh the page and try again.');
    }

    // Required fields validation
    $requiredFields = [
        'full_name', 'cnic', 'email', 'phone', 'date_of_birth', 'gender',
        'address', 'education_level', 'parent_name', 'parent_phone',
        'course_id', 'emergency_contact'
    ];

    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        $response['errors'] = ['Missing required fields: ' . implode(', ', $missingFields)];
        throw new Exception('Please fill all required fields.');
    }

    // Sanitize and validate inputs
    $full_name = sanitizeInput($_POST['full_name']);
    $cnic = sanitizeInput($_POST['cnic']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $date_of_birth = sanitizeInput($_POST['date_of_birth']);
    $gender = sanitizeInput($_POST['gender']);
    $address = sanitizeInput($_POST['address']);
    $education_level = sanitizeInput($_POST['education_level']);
    $previous_experience = !empty($_POST['previous_experience']) ? sanitizeInput($_POST['previous_experience']) : null;
    $parent_name = sanitizeInput($_POST['parent_name']);
    $parent_phone = sanitizeInput($_POST['parent_phone']);
    $emergency_contact = sanitizeInput($_POST['emergency_contact']);
    $course_id = intval($_POST['course_id']);
    $batch_id = !empty($_POST['batch_id']) ? intval($_POST['batch_id']) : null;

    // Input validation
    if (!validateCNIC($cnic)) {
        $response['errors'][] = 'cnic';
        throw new Exception('Invalid CNIC format. Use format: 12345-1234567-1');
    }

    if (!validateEmail($email)) {
        $response['errors'][] = 'email';
        throw new Exception('Invalid email address.');
    }

    if (!validatePhone($phone)) {
        $response['errors'][] = 'phone';
        throw new Exception('Invalid phone number. Use Pakistani format: 03XX-XXXXXXX');
    }

    if (!validatePhone($parent_phone)) {
        $response['errors'][] = 'parent_phone';
        throw new Exception('Invalid parent phone number.');
    }

    if (!validatePhone($emergency_contact)) {
        $response['errors'][] = 'emergency_contact';
        throw new Exception('Invalid emergency contact number.');
    }

    if (!validateDOB($date_of_birth)) {
        $response['errors'][] = 'date_of_birth';
        throw new Exception('You must be at least 16 years old to enroll.');
    }

    // Get database connection
    $conn = Database::getConnection();

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check if user already exists with this CNIC or email
        $checkQuery = "SELECT user_id FROM users WHERE cnic = ? OR email = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ss", $cnic, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('A user with this CNIC or email already exists.');
        }

        // Generate username from full name
        $nameParts = explode(' ', $full_name);
        $firstName = strtolower(preg_replace('/[^a-zA-Z]/', '', $nameParts[0]));
        $lastName = isset($nameParts[1]) ? strtolower(preg_replace('/[^a-zA-Z]/', '', $nameParts[1])) : 'user';
        $username = $firstName . '.' . $lastName . rand(100, 999);

        // Generate temporary password
        $tempPassword = bin2hex(random_bytes(8)); // 16 character temporary password

        // Hash password with salt
        $passwordHash = hash('sha256', $tempPassword . 'skills_way_salt');

        // Insert into users table
        $userQuery = "INSERT INTO users (username, email, phone, cnic, full_name, password_hash, user_type, account_status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'student', 'pending_verification')";
        
        $stmt = $conn->prepare($userQuery);
        $stmt->bind_param("ssssss", $username, $email, $phone, $cnic, $full_name, $passwordHash);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create user account: ' . $stmt->error);
        }
        
        $user_id = $stmt->insert_id;

        // Insert into user_profiles table
        $profileQuery = "INSERT INTO user_profiles 
                        (user_id, date_of_birth, gender, address, education_level, previous_experience, 
                         emergency_contact, parent_name, parent_phone) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($profileQuery);
        $stmt->bind_param("issssssss", 
            $user_id, $date_of_birth, $gender, $address, $education_level, 
            $previous_experience, $emergency_contact, $parent_name, $parent_phone);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create user profile: ' . $stmt->error);
        }

        // Get default batch if not specified
        if (!$batch_id) {
            $batchQuery = "SELECT batch_id FROM batches WHERE course_id = ? AND status = 'upcoming' ORDER BY start_date LIMIT 1";
            $stmt = $conn->prepare($batchQuery);
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $batchResult = $stmt->get_result();
            
            if ($batchResult->num_rows > 0) {
                $batchRow = $batchResult->fetch_assoc();
                $batch_id = $batchRow['batch_id'];
            } else {
                throw new Exception('No upcoming batches available for this course.');
            }
        }

        // Insert into enrollment_applications table
        $applicationQuery = "INSERT INTO enrollment_applications 
                            (user_id, course_id, batch_id, application_status, payment_status) 
                            VALUES (?, ?, ?, 'submitted', 'pending')";
        
        $stmt = $conn->prepare($applicationQuery);
        $stmt->bind_param("iii", $user_id, $course_id, $batch_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to submit application: ' . $stmt->error);
        }
        
        $application_id = $stmt->insert_id;

        // Log activity
        $activityQuery = "INSERT INTO user_activity_logs (user_id, activity_type, activity_details, ip_address) 
                         VALUES (?, 'enrollment_submitted', ?, ?)";
        $activityDetails = json_encode([
            'application_id' => $application_id,
            'course_id' => $course_id,
            'batch_id' => $batch_id
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $conn->prepare($activityQuery);
        $stmt->bind_param("iss", $user_id, $activityDetails, $ip_address);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Send email notification to admin (in production)
        // $this->sendAdminNotification($application_id, $full_name, $email);

        // Prepare success response
        $response['success'] = true;
        $response['message'] = 'Application submitted successfully! Your application ID is: ' . $application_id;
        $response['application_id'] = $application_id;
        $response['user_id'] = $user_id;
        $response['username'] = $username;
        $response['temp_password'] = $tempPassword; // Only for demo - remove in production

        // Store in session for success page
        $_SESSION['application_success'] = [
            'application_id' => $application_id,
            'full_name' => $full_name,
            'email' => $email
        ];

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Clear CSRF token after use
if (isset($_SESSION['csrf_token'])) {
    unset($_SESSION['csrf_token']);
}

// Return JSON response
echo json_encode($response);

// Redirect on success (if not AJAX)
if ($response['success'] && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Location: application-success.php');
    exit();
}

// Database connection class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $config = require 'config/database.php';
        
        $this->conn = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );
        
        if ($this->conn->connect_error) {
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }
        
        $this->conn->set_charset("utf8mb4");
    }
    
    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}
?>