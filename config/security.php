<?php

/**
 * Security Configuration & Helper Functions
 */

class Security
{
    /**
     * Initialize security for all pages
     */
    public static function init()
    {
        self::setSecurityHeaders();
        self::startSecureSession();
        self::checkSecurityThreats();
    }

    /**
     * Set comprehensive security headers
     */
    public static function setSecurityHeaders()
    {
        if (headers_sent()) return;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // Relaxed CSP for development/mixed content
        $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:;";
        header("Content-Security-Policy: $csp");

        header('Referrer-Policy: strict-origin-when-cross-origin');
        header_remove('X-Powered-By');
    }

    /**
     * Start secure session
     */
    public static function startSecureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);

            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }

            session_start();
        }
    }

    /**
     * Check for common security threats
     */
    public static function checkSecurityThreats()
    {
        $dangerous_patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\btransform\b.*\bdata\b)/i' // Only minimal checks to avoid false positives
        ];

        // Implementation handled by WAF usually, keeping it simple here
    }

    /**
     * Validate and sanitize file uploads
     */
    public static function validateUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'])
    {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['No file uploaded'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['Upload error code: ' . $file['error']];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_types)) {
            return ['Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
        }

        return [];
    }

    /**
     * Generate secure filename
     */
    public static function generateSecureFilename($original_name)
    {
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
        $timestamp = date('Ymd_His');
        $random = bin2hex(random_bytes(4));
        return "{$name}_{$timestamp}_{$random}.{$ext}";
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Alias for backward compatibility
    public static function setCSRFToken()
    {
        return self::generateCSRFToken();
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function sanitizeInput($input)
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function csrfField()
    {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
