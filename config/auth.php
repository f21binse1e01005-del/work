<?php
/**
 * PROFESSIONAL AUTHENTICATION CLASS
 * Uses MariaDB SHA2() compatible hashing with PHP hash('sha256')
 * @version 2.0
 */

class Authentication {
    private $db;
    private $session;
    
    public function __construct() {
        require_once 'database.php';
        require_once 'session.php';
        $database = new Database();
        $this->db = $database->getConnection();
        if (!$this->db) {
            // Don't throw exception, just log error
            error_log('Database connection not available in Authentication class');
            return;
        }
        $this->session = new SessionManager();
    }
    
    /**
     * Professional login with MariaDB SHA2 compatibility
     */
    public function login($username, $password, $remember = false) {
        // Input validation
        if (empty($username) || empty($password)) {
            return $this->errorResponse('Username and password are required');
        }
        
        try {
            // Find active user by username or email
            $query = "SELECT u.*, up.parent_name, up.parent_phone 
                     FROM users u 
                     LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                     WHERE (u.username = ? OR u.email = ?) 
                     AND u.account_status = 'active'
                     LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->logLoginAttempt(null, $username, 'failed_user_not_found', 'User not found');
                return $this->errorResponse('Invalid username or password');
            }
            
            // Verify password using Hybrid Method (Bcrypt OR Salted SHA256)
            $verification_success = false;

            if (strpos($user['password_hash'], '$2y$') === 0) {
                // Check if it's bcrypt hash
                $verification_success = password_verify($password, $user['password_hash']);
            } else {
                // Check if it's Salted SHA256 hash (legacy/custom)
                $hashed_input = hash('sha256', $password . 'skills_way_salt');
                
                // Also check unsalted SHA256 for backward compatibility with recent changes
                $hashed_input_unsalted = hash('sha256', $password);
                
                if (hash_equals($user['password_hash'], $hashed_input)) {
                    $verification_success = true; 
                } elseif (hash_equals($user['password_hash'], $hashed_input_unsalted)) {
                    // Start migration to salted hash if needed, but for now just allow login
                    $verification_success = true; 
                }
            }
            
            // Secure comparison result
            if (!$verification_success) {
                $this->logLoginAttempt($user['user_id'], $username, 'failed_password', 'Password mismatch');
                return $this->errorResponse('Invalid username or password');
            }
            
            // Successful login - setup session
            $this->setupUserSession($user);
            
            // Update last login
            $this->updateLastLogin($user['user_id']);
            
            // Set remember me token if requested
            if ($remember) {
                $this->setRememberMeToken($user['user_id']);
            }
            
            // Log successful login
            $this->logLoginAttempt($user['user_id'], $username, 'success', 'Login successful');
            
            return $this->successResponse('Login successful', [
                'user_id' => $user['user_id'],
                'user_type' => $user['user_type'],
                'redirect' => $this->getDashboardUrl($user['user_type'])
            ]);
            
        } catch (PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            return $this->errorResponse('Database error occurred');
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return $this->errorResponse('An unexpected error occurred');
        }
    }
    
    /**
     * Setup secure user session
     */
    private function setupUserSession($user) {
        // Store minimal user data in session
        $this->session->set('user_id', $user['user_id']);
        $this->session->set('username', $user['username']);
        $this->session->set('email', $user['email']);
        $this->session->set('full_name', $user['full_name']);
        $this->session->set('user_type', $user['user_type']);
        
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
    }
    
    /**
     * Set secure session cookie parameters
     */
    private function setSecureSessionCookie() {
        // Only set cookie parameters if session is not active
        if (session_status() === PHP_SESSION_NONE) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            $samesite = 'Strict';
            
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $samesite
            ]);
        }
    }
    
    /**
     * Log login attempt
     */
    private function logLoginAttempt($userId, $username, $status, $reason = '') {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            // Normalize status to allowed ENUM values to avoid DB truncation warnings
            $allowedStatuses = ['success', 'failed_password', 'failed_inactive', 'failed_locked'];
            $statusNormalized = in_array($status, $allowedStatuses, true) ? $status : 'failed_password';

            $query = "INSERT INTO login_logs (user_id, username, ip_address, user_agent, login_status, failure_reason) 
                     VALUES (:user_id, :username, :ip_address, :user_agent, :login_status, :failure_reason)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':username' => $username,
                ':ip_address' => $ip,
                ':user_agent' => substr($userAgent, 0, 500),
                ':login_status' => $statusNormalized,
                ':failure_reason' => substr($reason, 0, 255)
            ]);
        } catch (Exception $e) {
            // Don't break login if logging fails
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin($userId) {
        try {
            $query = "UPDATE users SET last_login = NOW(), updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':user_id' => $userId]);
        } catch (Exception $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }
    
    /**
     * Set remember me token
     */
    private function setRememberMeToken($userId) {
        try {
            $selector = bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);
            
            // Token valid for 30 days
            $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
            
            // Delete old tokens for this user
            $deleteQuery = "DELETE FROM remember_me_tokens WHERE user_id = :user_id";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->execute([':user_id' => $userId]);
            
            // Insert new token
            $insertQuery = "INSERT INTO remember_me_tokens (user_id, selector, hashed_token, expires_at) 
                           VALUES (:user_id, :selector, :hashed_token, :expires_at)";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->execute([
                ':user_id' => $userId,
                ':selector' => $selector,
                ':hashed_token' => $hashedToken,
                ':expires_at' => $expires
            ]);
            
            // Set cookie (30 days)
            $cookieValue = $selector . ':' . $token;
            setcookie('remember_me', $cookieValue, [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to set remember me token: " . $e->getMessage());
        }
    }
    
    /**
     * Validate remember me token
     */
    public function validateRememberMeToken() {
        if (!isset($_COOKIE['remember_me'])) {
            return false;
        }
        
        try {
            $parts = explode(':', $_COOKIE['remember_me']);
            if (count($parts) !== 2) {
                return false;
            }
            
            list($selector, $token) = $parts;
            
            // Find token in database
            $query = "SELECT rmt.*, u.* 
                     FROM remember_me_tokens rmt
                     JOIN users u ON rmt.user_id = u.user_id
                     WHERE rmt.selector = :selector 
                     AND rmt.expires_at > NOW() 
                     AND u.account_status = 'active'
                     LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':selector' => $selector]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                return false;
            }
            
            // Verify token
            if (hash_equals($tokenData['hashed_token'], hash('sha256', $token))) {
                $this->setupUserSession($tokenData);
                $this->updateLastLogin($tokenData['user_id']);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Remember me validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Professional registration method
     */
    public function register($userData) {
        // Validate required fields
        $required = ['full_name', 'email', 'cnic', 'password'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                return $this->errorResponse("Field '{$field}' is required");
            }
        }
        
        // Validate email
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Invalid email address');
        }
        
        // Validate CNIC format
        if (!preg_match('/^\d{5}-\d{7}-\d{1}$/', $userData['cnic'])) {
            return $this->errorResponse('Invalid CNIC format (12345-1234567-1)');
        }
        
        // Validate password strength
        if (strlen($userData['password']) < 8) {
            return $this->errorResponse('Password must be at least 8 characters');
        }
        
        try {
            // Begin transaction
            $this->db->beginTransaction();
            
            // Check for existing user
            $checkQuery = "SELECT user_id FROM users WHERE email = :email OR cnic = :cnic LIMIT 1";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([
                ':email' => $userData['email'],
                ':cnic' => $userData['cnic']
            ]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('User with this email or CNIC already exists');
            }
            
            // Generate username
            $username = $this->generateUsername($userData['full_name']);
            
            // Hash password (Salted SHA256)
            $passwordHash = hash('sha256', $userData['password'] . 'skills_way_salt');
            
            // Insert user
            $userQuery = "INSERT INTO users (
                username, email, phone, cnic, full_name, 
                password_hash, user_type, account_status
            ) VALUES (
                :username, :email, :phone, :cnic, :full_name,
                :password_hash, :user_type, :account_status
            )";
            
            $userStmt = $this->db->prepare($userQuery);
            $userStmt->execute([
                ':username' => $username,
                ':email' => $userData['email'],
                ':phone' => $userData['phone'] ?? null,
                ':cnic' => $userData['cnic'],
                ':full_name' => $userData['full_name'],
                ':password_hash' => $passwordHash,
                ':user_type' => $userData['user_type'] ?? 'student',
                ':account_status' => $userData['account_status'] ?? 'active'
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Insert profile if data provided
            if (!empty($userData['date_of_birth']) || !empty($userData['address'])) {
                $profileQuery = "INSERT INTO user_profiles (
                    user_id, date_of_birth, gender, address, 
                    education_level, parent_name, parent_phone
                ) VALUES (
                    :user_id, :date_of_birth, :gender, :address,
                    :education_level, :parent_name, :parent_phone
                )";
                
                $profileStmt = $this->db->prepare($profileQuery);
                $profileStmt->execute([
                    ':user_id' => $userId,
                    ':date_of_birth' => $userData['date_of_birth'] ?? null,
                    ':gender' => $userData['gender'] ?? null,
                    ':address' => $userData['address'] ?? null,
                    ':education_level' => $userData['education_level'] ?? null,
                    ':parent_name' => $userData['parent_name'] ?? null,
                    ':parent_phone' => $userData['parent_phone'] ?? null
                ]);
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Log activity
            $this->logUserActivity($userId, 'registration', [
                'source' => 'public_registration',
                'email' => $userData['email']
            ]);
            
            return $this->successResponse('Registration successful', [
                'user_id' => $userId,
                'username' => $username,
                'auto_login' => $userData['auto_login'] ?? false
            ]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }
    
    /**
     * Generate unique username
     */
    private function generateUsername($fullName) {
        $parts = explode(' ', $fullName);
        $firstName = strtolower(preg_replace('/[^a-z]/', '', $parts[0]));
        $lastName = isset($parts[1]) ? strtolower(preg_replace('/[^a-z]/', '', $parts[1])) : 'user';
        
        $baseUsername = $firstName . '.' . $lastName;
        $username = $baseUsername;
        $counter = 1;
        
        // Check if username exists
        while (true) {
            $checkQuery = "SELECT COUNT(*) as count FROM users WHERE username = :username";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([':username' => $username]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                break;
            }
            
            $username = $baseUsername . $counter;
            $counter++;
            
            if ($counter > 100) {
                // Fallback to random username
                $username = $firstName . '.' . bin2hex(random_bytes(3));
                break;
            }
        }
        
        return $username;
    }
    
    /**
     * Log user activity
     */
    private function logUserActivity($userId, $activityType, $details = []) {
        try {
            $query = "INSERT INTO user_activity_logs (user_id, activity_type, activity_details, ip_address, user_agent) 
                     VALUES (:user_id, :activity_type, :activity_details, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':activity_type' => $activityType,
                ':activity_details' => json_encode($details),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
    
    /**
     * Get dashboard URL based on user type
     */
    private function getDashboardUrl($userType) {
        $dashboards = [
            'admin' => 'admin/dashboard.php',
            'teacher' => 'teacher/dashboard.php',
            'student' => 'student/dashboard.php'
        ];
        
        return $dashboards[$userType] ?? 'dashboard.php';
    }
    
    /**
     * Standardized error response
     */
    private function errorResponse($message) {
        return [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Standardized success response
     */
    private function successResponse($message, $data = []) {
        return [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Clear remember me token
        if (isset($_COOKIE['remember_me'])) {
            $parts = explode(':', $_COOKIE['remember_me']);
            $selector = $parts[0] ?? '';
            
            if ($selector) {
                try {
                    $query = "DELETE FROM remember_me_tokens WHERE selector = :selector";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([':selector' => $selector]);
                } catch (Exception $e) {
                    error_log("Failed to delete remember me token: " . $e->getMessage());
                }
            }
            
            // Clear cookie
            setcookie('remember_me', '', time() - 3600, '/');
        }
        
        // Destroy session
        $this->session->destroy();
        
        return $this->successResponse('Logout successful');
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return $this->session->isLoggedIn();
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        try {
            $query = "SELECT u.*, up.* 
                     FROM users u 
                     LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                     WHERE u.user_id = :user_id 
                     AND u.account_status = 'active'
                     LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':user_id' => $this->session->get('user_id')]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get user data: " . $e->getMessage());
            return null;
        }
    }
}
?>