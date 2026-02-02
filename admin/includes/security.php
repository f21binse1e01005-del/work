<?php

/**
 * Security Helper Functions
 * Advanced security utilities for PHP applications
 * File: includes/security.php
 */

/**
 * Security Configuration
 */
class SecurityConfig
{
    const HASH_ALGO = 'sha256';
    const HASH_COST = 12;
    const TOKEN_LENGTH = 32;
    const SALT_LENGTH = 16;
    const ENCRYPTION_METHOD = 'AES-256-GCM';
    const ENCRYPTION_KEY_LENGTH = 32;
    const NONCE_LENGTH = SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTE;
    const RATE_LIMIT_WINDOW = 3600; // 1 hour in seconds
    const RATE_LIMIT_MAX_REQUESTS = 100;
    const SESSION_TIMEOUT = 1800; // 30 minutes
    const SESSION_REGENERATE_INTERVAL = 300; // 5 minutes
    const CSP_DIRECTIVES = [
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com",
        'style-src' => "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        'img-src' => "'self' data: https:",
        'font-src' => "'self' https://fonts.gstatic.com",
        'connect-src' => "'self'",
        'frame-src' => "'none'",
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'none'",
        'block-all-mixed-content' => true,
        'upgrade-insecure-requests' => true
    ];
}

/**
 * Initialize security settings
 */
function initializeSecurity()
{
    // Set secure session settings if session not started
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isHTTPS() ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_lifetime', 0);

        session_start();
    } else {
        // Session already started, regenerate if needed
    }

    // Regenerate session ID periodically
    sessionRegeneration();

    // Set security headers
    setSecurityHeaders();

    // Initialize CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_time'] = time();
    }

    // Check session timeout
    checkSessionTimeout();

    // Apply rate limiting
    applyRateLimit();

    // Validate request origin
    validateRequestOrigin();

    // Log security events
    logSecurityEvent('session_init', ['ip' => getClientIP()]);
}

/**
 * Set security headers
 */
function setSecurityHeaders()
{
    // Content Security Policy
    $cspHeader = buildCSPHeader();
    header("Content-Security-Policy: $cspHeader");

    // Other security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

    // HSTS for HTTPS
    if (isHTTPS()) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

/**
 * Build Content Security Policy header
 */
function buildCSPHeader()
{
    $directives = SecurityConfig::CSP_DIRECTIVES;
    $csp = [];

    foreach ($directives as $directive => $value) {
        if (is_bool($value)) {
            if ($value) {
                $csp[] = $directive;
            }
        } else {
            $csp[] = "$directive $value";
        }
    }

    return implode('; ', $csp);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(SecurityConfig::TOKEN_LENGTH));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(SecurityConfig::TOKEN_LENGTH));
    } else {
        // Fallback (less secure)
        return hash('sha256', uniqid(mt_rand(), true) . session_id());
    }
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token)
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    // Check if token matches
    $isValid = hash_equals($_SESSION['csrf_token'], $token);

    // Check token age (optional, for extra security)
    if ($isValid && isset($_SESSION['csrf_token_time'])) {
        $tokenAge = time() - $_SESSION['csrf_token_time'];
        if ($tokenAge > 3600) { // Token expires after 1 hour
            $isValid = false;
            // Regenerate token
            $_SESSION['csrf_token'] = generateCSRFToken();
            $_SESSION['csrf_token_time'] = time();
        }
    }

    if (!$isValid) {
        logSecurityEvent('csrf_validation_failed', [
            'expected' => $_SESSION['csrf_token'],
            'received' => $token,
            'ip' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    return $isValid;
}

/**
 * Get CSRF token for forms
 */
function getCSRFTokenField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

/**
 * Input sanitization and validation
 */

/**
 * Clean input data
 */
function cleanInput($data, $type = 'string')
{
    if (is_null($data) || $data === '') {
        return $data;
    }

    if (is_array($data)) {
        return array_map(fn($item) => cleanInput($item, $type), $data);
    }

    // Remove unwanted characters
    $data = strip_tags($data);
    $data = trim($data);

    switch ($type) {
        case 'email':
            $data = filter_var($data, FILTER_SANITIZE_EMAIL);
            break;

        case 'url':
            $data = filter_var($data, FILTER_SANITIZE_URL);
            break;

        case 'int':
            $data = filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            break;

        case 'float':
            $data = filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            break;

        case 'string':
        default:
            $data = filter_var($data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            break;
    }

    return $data;
}

/**
 * Validate input with rules
 */
function validateInput($data, $rules)
{
    $errors = [];
    $cleanData = [];

    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        $fieldErrors = [];

        // Check required
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $fieldErrors[] = "The $field field is required.";
        }

        if (!empty($value)) {
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fieldErrors[] = "Invalid email format for $field.";
                        }
                        break;

                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $fieldErrors[] = "Invalid URL format for $field.";
                        }
                        break;

                    case 'int':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $fieldErrors[] = "The $field must be an integer.";
                        }
                        break;

                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $fieldErrors[] = "The $field must be a number.";
                        }
                        break;

                    case 'date':
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if (!$date || $date->format('Y-m-d') !== $value) {
                            $fieldErrors[] = "Invalid date format for $field. Use YYYY-MM-DD.";
                        }
                        break;

                    case 'phone':
                        if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $value)) {
                            $fieldErrors[] = "Invalid phone number format for $field.";
                        }
                        break;

                    case 'cnic':
                        if (!preg_match('/^\d{5}-\d{7}-\d{1}$/', $value) && !preg_match('/^\d{13}$/', $value)) {
                            $fieldErrors[] = "Invalid CNIC format for $field.";
                        }
                        break;
                }
            }

            // Length validation
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $fieldErrors[] = "The $field must be at least {$rule['min_length']} characters.";
            }

            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $fieldErrors[] = "The $field must not exceed {$rule['max_length']} characters.";
            }

            // Pattern validation
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $fieldErrors[] = "Invalid format for $field.";
            }

            // Custom validation
            if (isset($rule['custom']) && is_callable($rule['custom'])) {
                $customResult = $rule['custom']($value);
                if ($customResult !== true) {
                    $fieldErrors[] = $customResult;
                }
            }

            // Sanitize value
            if (empty($fieldErrors)) {
                $cleanData[$field] = cleanInput($value, $rule['type'] ?? 'string');
            }
        }

        if (!empty($fieldErrors)) {
            $errors[$field] = $fieldErrors;
        }
    }

    return [
        'success' => empty($errors),
        'errors' => $errors,
        'data' => $cleanData
    ];
}

/**
 * Password security
 */

/**
 * Hash password with salt
 */
function hashPassword($password)
{
    $options = [
        'cost' => SecurityConfig::HASH_COST,
        'salt' => random_bytes(SecurityConfig::SALT_LENGTH)
    ];

    return password_hash($password, PASSWORD_BCRYPT, $options);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Check password strength
 */
function checkPasswordStrength($password)
{
    $score = 0;
    $feedback = [];

    // Length check
    if (strlen($password) >= 12) {
        $score += 2;
    } elseif (strlen($password) >= 8) {
        $score += 1;
        $feedback[] = "Consider using at least 12 characters.";
    } else {
        $feedback[] = "Password should be at least 8 characters long.";
    }

    // Upper & lower case
    if (preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = "Use both uppercase and lowercase letters.";
    }

    // Numbers
    if (preg_match('/\d/', $password)) {
        $score += 1;
    } else {
        $feedback[] = "Include at least one number.";
    }

    // Special characters
    if (preg_match('/[^A-Za-z0-9]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = "Include at least one special character.";
    }

    // Common password check
    $commonPasswords = ['password', '123456', 'qwerty', 'letmein', 'welcome'];
    if (in_array(strtolower($password), $commonPasswords)) {
        $score = 0;
        $feedback[] = "This password is too common. Choose a different one.";
    }

    return [
        'score' => $score,
        'strength' => match (true) {
            $score >= 5 => 'very_strong',
            $score >= 4 => 'strong',
            $score >= 3 => 'moderate',
            $score >= 2 => 'weak',
            default => 'very_weak'
        },
        'feedback' => $feedback
    ];
}

/**
 * Generate secure random password
 */
function generateSecurePassword($length = 16)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password;
}

/**
 * Encryption & Decryption
 */

/**
 * Encrypt data
 */
function encryptData($data, $key = null)
{
    if ($key === null) {
        $key = getEncryptionKey();
    }

    if (function_exists('sodium_crypto_aead_aes256gcm_encrypt')) {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTE);
        $encrypted = sodium_crypto_aead_aes256gcm_encrypt(
            $data,
            '', // Additional data
            $nonce,
            $key
        );

        return base64_encode($nonce . $encrypted);
    } elseif (function_exists('openssl_encrypt')) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(SecurityConfig::ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt(
            $data,
            SecurityConfig::ENCRYPTION_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    throw new Exception('Encryption not supported on this system.');
}

/**
 * Decrypt data
 */
function decryptData($encryptedData, $key = null)
{
    if ($key === null) {
        $key = getEncryptionKey();
    }

    $data = base64_decode($encryptedData);

    if (function_exists('sodium_crypto_aead_aes256gcm_decrypt')) {
        $nonce = substr($data, 0, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTE);
        $ciphertext = substr($data, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTE);

        return sodium_crypto_aead_aes256gcm_decrypt(
            $ciphertext,
            '', // Additional data
            $nonce,
            $key
        );
    } elseif (function_exists('openssl_decrypt')) {
        $ivLength = openssl_cipher_iv_length(SecurityConfig::ENCRYPTION_METHOD);
        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);

        return openssl_decrypt(
            $ciphertext,
            SecurityConfig::ENCRYPTION_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    throw new Exception('Decryption not supported on this system.');
}

/**
 * Get encryption key
 */
function getEncryptionKey()
{
    if (defined('ENCRYPTION_KEY')) {
        return ENCRYPTION_KEY;
    }

    // Generate and store key if not exists
    $keyFile = __DIR__ . '/../config/encryption.key';

    if (!file_exists($keyFile)) {
        $key = random_bytes(SecurityConfig::ENCRYPTION_KEY_LENGTH);
        file_put_contents($keyFile, base64_encode($key));
        chmod($keyFile, 0600);
    } else {
        $key = base64_decode(file_get_contents($keyFile));
    }

    return $key;
}

/**
 * Session security
 */

/**
 * Regenerate session ID periodically
 */
function sessionRegeneration()
{
    $lastRegeneration = $_SESSION['last_regeneration'] ?? 0;
    $currentTime = time();

    if ($currentTime - $lastRegeneration > SecurityConfig::SESSION_REGENERATE_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = $currentTime;
        $_SESSION['session_start'] = $currentTime;
    }
}

/**
 * Check session timeout
 */
function checkSessionTimeout()
{
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    $inactiveTimeout = SecurityConfig::SESSION_TIMEOUT;
    $currentTime = time();

    if (isset($_SESSION['user_id'])) {
        // User is logged in
        if ($currentTime - $_SESSION['last_activity'] > $inactiveTimeout) {
            // Session expired
            session_destroy();
            session_start();
            $_SESSION['session_expired'] = true;

            logSecurityEvent('session_expired', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip' => getClientIP()
            ]);

            if (isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['error' => 'Session expired. Please refresh the page.']);
                exit;
            } else {
                header('Location: login.php?expired=1');
                exit;
            }
        }
    }

    // Update last activity time
    $_SESSION['last_activity'] = $currentTime;
}

/**
 * Destroy session securely
 */
function destroySession()
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

/**
 * Rate limiting
 */

/**
 * Apply rate limiting
 */
function applyRateLimit($identifier = null, $window = null, $maxRequests = null)
{
    if ($identifier === null) {
        $identifier = getRateLimitIdentifier();
    }

    if ($window === null) {
        $window = SecurityConfig::RATE_LIMIT_WINDOW;
    }

    if ($maxRequests === null) {
        $maxRequests = SecurityConfig::RATE_LIMIT_MAX_REQUESTS;
    }

    $key = 'rate_limit_' . hash('sha256', $identifier);
    $redis = getRedisConnection();

    if ($redis) {
        // Redis implementation
        $current = $redis->get($key);

        if (!$current) {
            $redis->setex($key, $window, 1);
            return true;
        }

        if ($current >= $maxRequests) {
            logSecurityEvent('rate_limit_exceeded', [
                'identifier' => $identifier,
                'requests' => $current,
                'ip' => getClientIP()
            ]);

            http_response_code(429);
            echo 'Too many requests. Please try again later.';
            exit;
        }

        $redis->incr($key);
    } else {
        // File-based implementation
        $cacheDir = __DIR__ . '/../cache/rate_limit/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . $key;
        $currentTime = time();

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);

            if ($currentTime - $data['timestamp'] > $window) {
                $data = ['count' => 1, 'timestamp' => $currentTime];
            } else {
                $data['count']++;

                if ($data['count'] > $maxRequests) {
                    logSecurityEvent('rate_limit_exceeded', [
                        'identifier' => $identifier,
                        'requests' => $data['count'],
                        'ip' => getClientIP()
                    ]);

                    http_response_code(429);
                    echo 'Too many requests. Please try again later.';
                    exit;
                }
            }
        } else {
            $data = ['count' => 1, 'timestamp' => $currentTime];
        }

        file_put_contents($file, json_encode($data));
    }

    return true;
}

/**
 * Get rate limit identifier
 */
function getRateLimitIdentifier()
{
    return getClientIP() . '_' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest');
}

/**
 * Request validation
 */

/**
 * Validate request origin
 */
function validateRequestOrigin()
{
    // Check if request is from same origin
    $serverName = $_SERVER['SERVER_NAME'];
    $allowedOrigins = [
        $serverName,
        'localhost',
        '127.0.0.1'
    ];

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        $port = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_PORT);

        // If origin has port, check against server port if available
        if ($port && $port != 80 && $port != 443) {
            // For development environments with custom ports
            // Verify the host matches
        }

        if (!in_array($origin, $allowedOrigins) && $origin !== $_SERVER['SERVER_ADDR']) {
            logSecurityEvent('invalid_origin', [
                'origin' => $_SERVER['HTTP_ORIGIN'],
                'parsed_origin' => $origin,
                'allowed' => $allowedOrigins,
                'ip' => getClientIP()
            ]);

            // Allow localhost in development
            if ($origin !== 'localhost' && $origin !== '127.0.0.1') {
                http_response_code(403);
                exit;
            }
        }
    }

    // Check referer for form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $parsedReferer = parse_url($referer, PHP_URL_HOST);

        if (!in_array($parsedReferer, $allowedOrigins)) {
            logSecurityEvent('invalid_referer', [
                'referer' => $referer,
                'allowed' => $allowedOrigins,
                'ip' => getClientIP()
            ]);

            http_response_code(403);
            exit;
        }
    }
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880)
{
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File size exceeds limit.',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
            default => 'Unknown upload error.'
        };
        return ['success' => false, 'errors' => $errors];
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds maximum allowed size of ' . formatBytes($maxSize);
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes)) {
        $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
    }

    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];

    if (!in_array($extension, $allowedExtensions)) {
        $errors[] = 'File extension not allowed.';
    }

    // Validate image dimensions (for images only)
    if (strpos($mime, 'image/') === 0) {
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            $errors[] = 'Invalid image file.';
        } else {
            // Check dimensions if needed
            list($width, $height) = $imageInfo;
            if ($width > 5000 || $height > 5000) {
                $errors[] = 'Image dimensions too large. Maximum: 5000x5000 pixels';
            }
        }
    }

    // Scan for malware (if ClamAV is available)
    if (function_exists('clamav_scanfile')) {
        $scanResult = clamav_scanfile($file['tmp_name']);
        if ($scanResult !== false) {
            $errors[] = 'File contains malware: ' . $scanResult;
        }
    }

    return [
        'success' => empty($errors),
        'errors' => $errors,
        'mime' => $mime,
        'extension' => $extension,
        'size' => $file['size']
    ];
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename)
{
    $filename = preg_replace('/[^a-zA-Z0-9\-\._]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename);
    $filename = trim($filename, '._');

    // Prevent directory traversal
    $filename = basename($filename);

    // Add timestamp for uniqueness
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);

    return $name . '_' . time() . '.' . $extension;
}

/**
 * Utility functions
 */

/**
 * Get client IP address
 */
function getClientIP()
{
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (isset($_SERVER[$header])) {
            foreach (explode(',', $_SERVER[$header]) as $ip) {
                $ip = trim($ip);

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Check if HTTPS is enabled
 */
function isHTTPS()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $_SERVER['SERVER_PORT'] == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get Redis connection (if available)
 */
function getRedisConnection()
{
    static $redis = null;

    if ($redis === null && class_exists('Redis')) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379, 1);
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        } catch (Exception $e) {
            $redis = false;
            logSecurityEvent('redis_connection_failed', ['error' => $e->getMessage()]);
        }
    }

    return $redis ?: false;
}

/**
 * Logging
 */

/**
 * Log security events
 */
function logSecurityEvent($event, $data = [])
{
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'data' => $data,
        'ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null
    ];

    $logLine = json_encode($logData) . PHP_EOL;

    // Log to file
    $logDir = __DIR__ . '/../logs/security/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . date('Y-m-d') . '.log';
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

    // Log to syslog if available
    if (function_exists('syslog')) {
        // LOG_LOCAL0 is not defined on Windows
        $facility = defined('LOG_LOCAL0') ? LOG_LOCAL0 : LOG_USER;
        openlog('security', LOG_PID | LOG_PERROR, $facility);
        syslog(LOG_WARNING, "Security event: $event - " . json_encode($data));
        closelog();
    }
}

/**
 * Log failed login attempt
 */
function logFailedLogin($username, $reason = 'invalid_credentials')
{
    logSecurityEvent('login_failed', [
        'username' => $username,
        'reason' => $reason,
        'ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
}

/**
 * SQL Injection prevention
 */

/**
 * Prepare SQL with parameter binding
 */
function prepareSQL($db, $query, $params = [])
{
    $stmt = $db->prepare($query);

    if (!empty($params)) {
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                // Positional parameters
                $stmt->bindValue($key + 1, $value, getPDOParamType($value));
            } else {
                // Named parameters
                $stmt->bindValue(':' . $key, $value, getPDOParamType($value));
            }
        }
    }

    return $stmt;
}

/**
 * Get PDO parameter type
 */
function getPDOParamType($value)
{
    if (is_int($value)) {
        return PDO::PARAM_INT;
    } elseif (is_bool($value)) {
        return PDO::PARAM_BOOL;
    } elseif (is_null($value)) {
        return PDO::PARAM_NULL;
    } else {
        return PDO::PARAM_STR;
    }
}

/**
 * Escape SQL LIKE wildcards
 */
function escapeLikeWildcards($string)
{
    return str_replace(
        ['\\', '%', '_'],
        ['\\\\', '\\%', '\\_'],
        $string
    );
}

/**
 * XSS Prevention
 */

/**
 * Output escaping for different contexts
 */
function escapeOutput($data, $context = 'html')
{
    switch ($context) {
        case 'html':
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        case 'attr':
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        case 'js':
            return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        case 'css':
            // Escape for CSS contexts
            return preg_replace('/[^a-zA-Z0-9]/', '', $data);

        case 'url':
            return urlencode($data);

        default:
            return $data;
    }
}

/**
 * Security Headers Helper
 */

/**
 * Add security headers for API responses
 */
function addAPISecurityHeaders()
{
    header("Access-Control-Allow-Origin: " . (isHTTPS() ? "https://" : "http://") . $_SERVER['HTTP_HOST']);
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
}

/**
 * Database security
 */

/**
 * Validate database identifier (table/column names)
 */
function validateDBIdentifier($identifier)
{
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier);
}

/**
 * Check for SQL injection patterns
 */
function detectSQLInjection($string)
{
    $patterns = [
        '/(union|select|insert|update|delete|drop|create|alter)\s+.*/i',
        '/\'\s*or\s*\'[^=]+\'=\'[^=]+\'/i',
        '/\b(exec|execute|sp_executesql)\b/i',
        '/--|\/\*|\*\//',
        '/;\s*(drop|delete|update|insert)/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $string)) {
            return true;
        }
    }

    return false;
}

/**
 * Permission and authorization
 */

/**
 * Check if user has permission
 */
function hasPermission($permission, $userPermissions = null)
{
    // Grant full access to admins
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        return true;
    }

    if ($userPermissions === null) {
        $userPermissions = $_SESSION['permissions'] ?? [];
    }

    if (in_array('*', $userPermissions)) {
        return true; // Super admin
    }

    return in_array($permission, $userPermissions);
}

/**
 * Check if user has role
 */
function hasRole($role, $userRoles = null)
{
    if ($userRoles === null) {
        $userRoles = $_SESSION['roles'] ?? [];
    }

    return in_array($role, $userRoles);
}

/**
 * Generate permission token
 */
function generatePermissionToken($userId, $permissions)
{
    $data = [
        'user_id' => $userId,
        'permissions' => $permissions,
        'expires' => time() + 3600, // 1 hour
        'ip' => getClientIP()
    ];

    $token = encryptData(json_encode($data));

    // Store token hash for validation
    $_SESSION['permission_token_hash'] = hash('sha256', $token);

    return $token;
}

/**
 * Validate permission token
 */
function validatePermissionToken($token)
{
    if (hash('sha256', $token) !== ($_SESSION['permission_token_hash'] ?? '')) {
        return false;
    }

    try {
        $data = json_decode(decryptData($token), true);

        if ($data['expires'] < time()) {
            return false;
        }

        if ($data['ip'] !== getClientIP()) {
            return false;
        }

        return $data;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Initialize security on every page
 */
initializeSecurity();
