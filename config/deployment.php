<?php
/**
 * Deployment Configuration
 */

class DeploymentConfig {
    
    // Environment configurations
    public static function getConfig($environment = 'production') {
        $configs = [
            'development' => [
                'debug' => true,
                'display_errors' => true,
                'log_errors' => true,
                'database' => [
                    'host' => 'localhost',
                    'name' => 'skills_way_vocational',
                    'user' => 'skills_user',
                    'pass' => 'SkillsWay@2024'
                ],
                'email' => [
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 587,
                    'username' => 'dev@skillsway.edu.pk',
                    'password' => 'dev_password'
                ],
                'security' => [
                    'ssl_required' => false,
                    'session_timeout' => 1800
                ]
            ],
            
            'production' => [
                'debug' => false,
                'display_errors' => false,
                'log_errors' => true,
                'database' => [
                    'host' => 'localhost',
                    'name' => 'skills_way_prod',
                    'user' => 'prod_user',
                    'pass' => 'CHANGE_IN_PRODUCTION'
                ],
                'email' => [
                    'smtp_host' => 'smtp.skillsway.edu.pk',
                    'smtp_port' => 587,
                    'username' => 'noreply@skillsway.edu.pk',
                    'password' => 'CHANGE_IN_PRODUCTION'
                ],
                'security' => [
                    'ssl_required' => true,
                    'session_timeout' => 900
                ]
            ]
        ];
        
        return $configs[$environment] ?? $configs['production'];
    }
    
    // Pre-deployment checks
    public static function runChecks() {
        $checks = [];
        
        // PHP Version
        $checks['php_version'] = [
            'name' => 'PHP Version',
            'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'message' => 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4.0', '>=') ? ' (OK)' : ' (Upgrade required)')
        ];
        
        // Required Extensions
        $extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl'];
        foreach ($extensions as $ext) {
            $checks["ext_$ext"] = [
                'name' => "Extension: $ext",
                'status' => extension_loaded($ext),
                'message' => extension_loaded($ext) ? 'Loaded' : 'Missing'
            ];
        }
        
        // Directory Permissions
        $directories = ['uploads', 'logs', 'backups'];
        foreach ($directories as $dir) {
            $path = __DIR__ . "/../$dir";
            $writable = is_dir($path) && is_writable($path);
            $checks["dir_$dir"] = [
                'name' => "Directory: $dir",
                'status' => $writable,
                'message' => $writable ? 'Writable' : 'Not writable'
            ];
        }
        
        // Configuration Files
        $configs = ['database.php', 'security.php', 'email.php', 'sms.php'];
        foreach ($configs as $config) {
            $exists = file_exists(__DIR__ . "/../config/$config");
            $checks["config_$config"] = [
                'name' => "Config: $config",
                'status' => $exists,
                'message' => $exists ? 'Exists' : 'Missing'
            ];
        }
        
        return $checks;
    }
    
    // Generate .htaccess for production
    public static function generateHtaccess() {
        return '# Skills Way LMS - Production .htaccess
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Hide sensitive files
<Files ~ "^\.">
    Order allow,deny
    Deny from all
</Files>

<Files ~ "\.(sql|log|bak)$">
    Order allow,deny
    Deny from all
</Files>

# PHP Security
php_flag display_errors Off
php_flag log_errors On
php_value error_log /path/to/logs/php_errors.log

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache Control
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</IfModule>
';
    }
}
?>