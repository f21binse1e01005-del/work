<?php
/**
 * Enhanced Middleware for Role-Based Access Control & Security
 */

require_once 'session.php';
require_once 'database.php';

class Middleware {
    private $session;
    private $db;
    
    public function __construct() {
        $this->session = new SessionManager();
        $this->db = (new Database())->getConnection();
    }
    
    /**
     * Admin access middleware
     */
    public static function admin_access() {
        $middleware = new self();
        $middleware->requireLogin();
        
        if ($middleware->session->get('user_type') !== 'admin') {
            $middleware->logSecurityEvent('unauthorized_admin_access');
            $middleware->redirectToAccessDenied();
        }
        
        $middleware->logActivity('admin_page_access', ['page' => $_SERVER['REQUEST_URI']]);
    }
    
    /**
     * Teacher access middleware
     */
    public static function teacher_access() {
        $middleware = new self();
        $middleware->requireLogin();
        
        $user_type = $middleware->session->get('user_type');
        if (!in_array($user_type, ['admin', 'teacher'])) {
            $middleware->logSecurityEvent('unauthorized_teacher_access');
            $middleware->redirectToAccessDenied();
        }
        
        $middleware->logActivity('teacher_page_access', ['page' => $_SERVER['REQUEST_URI']]);
    }
    
    /**
     * Student access middleware
     */
    public static function student_access() {
        $middleware = new self();
        $middleware->requireLogin();
        
        $user_type = $middleware->session->get('user_type');
        if (!in_array($user_type, ['admin', 'teacher', 'student'])) {
            $middleware->logSecurityEvent('unauthorized_student_access');
            $middleware->redirectToAccessDenied();
        }
        
        $middleware->logActivity('student_page_access', ['page' => $_SERVER['REQUEST_URI']]);
    }
    
    /**
     * Secure file upload validation
     */
    public static function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = 'No file uploaded';
            return $errors;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $file['error'];
            return $errors;
        }
        
        // Validate file size (10MB max)
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            $errors[] = 'File size exceeds 10MB limit';
        }
        
        // Validate file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_types)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowed_types);
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (isset($allowed_mimes[$file_ext]) && $mime_type !== $allowed_mimes[$file_ext]) {
            $errors[] = 'File content does not match extension';
        }
        
        // Check for malicious content
        $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
        if (strpos($content, '<?php') !== false || strpos($content, '<script') !== false) {
            $errors[] = 'Potentially malicious file content detected';
        }
        
        return $errors;
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        // Prevent XSS attacks
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");
        
        // HTTPS enforcement (if available)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Session timeout check
     */
    public function checkSessionTimeout($timeout_minutes = 30) {
        $last_activity = $this->session->get('last_activity');
        
        if ($last_activity && (time() - $last_activity) > ($timeout_minutes * 60)) {
            $this->logActivity('session_timeout');
            $this->session->destroy();
            header('Location: /login.php?timeout=1');
            exit;
        }
        
        $this->session->set('last_activity', time());
    }
    
    /**
     * CSRF token validation
     */
    public function validateCSRF($token) {
        if (!$this->session->validateCSRFToken($token)) {
            $this->logSecurityEvent('csrf_token_mismatch');
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
    
    /**
     * Rate limiting
     */
    public function rateLimit($action, $max_attempts = 5, $window_minutes = 15) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "rate_limit_{$action}_{$ip}";
        
        $attempts = $this->session->get($key, []);
        $current_time = time();
        
        // Remove old attempts outside the window
        $attempts = array_filter($attempts, function($timestamp) use ($current_time, $window_minutes) {
            return ($current_time - $timestamp) < ($window_minutes * 60);
        });
        
        if (count($attempts) >= $max_attempts) {
            $this->logSecurityEvent('rate_limit_exceeded', ['action' => $action]);
            http_response_code(429);
            die('Rate limit exceeded. Please try again later.');
        }
        
        $attempts[] = $current_time;
        $this->session->set($key, $attempts);
    }
    
    /**
     * Input sanitization
     */
    public static function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Require user to be logged in
     */
    private function requireLogin($redirect = '/login.php') {
        if (!$this->session->isLoggedIn()) {
            $this->session->set('redirect_after_login', $_SERVER['REQUEST_URI']);
            header("Location: $redirect");
            exit;
        }
        
        $this->checkSessionTimeout();
    }
    
    /**
     * Redirect to access denied page
     */
    private function redirectToAccessDenied() {
        header('HTTP/1.0 403 Forbidden');
        header('Location: /access-denied.php');
        exit;
    }
    
    /**
     * Get current user info
     */
    public function getCurrentUser() {
        if ($this->session->isLoggedIn()) {
            return [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username'),
                'user_type' => $this->session->get('user_type'),
                'full_name' => $this->session->get('full_name')
            ];
        }
        return null;
    }
    
    /**
     * Log user activity
     */
    public function logActivity($activity_type, $details = []) {
        $user = $this->getCurrentUser();
        
        if ($user && $this->db) {
            try {
                $sql = "INSERT INTO user_activity_logs (user_id, activity_type, activity_details, ip_address, user_agent) 
                        VALUES (:user_id, :activity_type, :details, :ip, :agent)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':user_id' => $user['user_id'],
                    ':activity_type' => $activity_type,
                    ':details' => json_encode($details),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                error_log("Activity log error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($event_type, $details = []) {
        if ($this->db) {
            try {
                $sql = "INSERT INTO security_logs (event_type, user_id, ip_address, user_agent, event_details) 
                        VALUES (:event_type, :user_id, :ip, :agent, :details)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':event_type' => $event_type,
                    ':user_id' => $this->session->get('user_id'),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    ':details' => json_encode($details)
                ]);
            } catch (Exception $e) {
                error_log("Security log error: " . $e->getMessage());
            }
        }
    }
}
?>