<?php

/**
 * Session Manager Class
 * Handles session management with security features
 */

class SessionManager
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            session_start();
        }

        $this->initializeSession();
    }

    private function initializeSession()
    {
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } else if (time() - $_SESSION['created'] > 1800) {
            // Regenerate session every 30 mins
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public function remove($key)
    {
        unset($_SESSION[$key]);
    }

    public function destroy()
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    public function isLoggedIn()
    {
        return !empty($_SESSION['user_id']);
    }

    public function requireAuth()
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /login.php');
            exit;
        }
    }

    public function requireRole($role)
    {
        $this->requireAuth();
        if (($this->get('user_type') ?? '') !== $role) {
            header('HTTP/1.1 403 Forbidden');
            echo "Access Denied: You do not have permission to access this page.";
            exit;
        }
    }

    public function flash($key, $message = null)
    {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
        } else {
            $msg = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Set CSRF token (Alias for generate for backward compatibility)
     */
    public function setCSRFToken()
    {
        return $this->generateCSRFToken();
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($event, $data = [])
    {
        // For now, we'll just use error_log
        // In a real app, this might write to a dedicated log file or database
        $logMessage = sprintf(
            "[Security] %s: %s - Data: %s - IP: %s - User: %s",
            $event,
            date('Y-m-d H:i:s'),
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $this->get('user_id') ?? 'guest'
        );
        error_log($logMessage);
    }
}

// Global helper function
function session()
{
    static $instance = null;
    if ($instance === null) {
        $instance = new SessionManager();
    }
    return $instance;
}
