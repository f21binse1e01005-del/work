<?php

/**
 * Database Configuration for Skills Way LMS
 * Enhanced with Singleton pattern, proper error handling, and reconnection logic
 */

class Database
{
    // Database configuration
    private $host = "localhost";
    private $db_name = "skills_way_vocational";
    private $username = "root";
    private $password = "";

    // Connection instance
    private $conn = null;

    // Singleton instance
    private static $instance = null;

    // Connection options
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::ATTR_TIMEOUT => 30
    ];

    // Constructor - Public for backward compatibility
    public function __construct()
    {
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect()
    {
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, $this->options);
            return true;
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
            return false;
        }
    }

    /**
     * Get database connection
     */
    public function getConnection()
    {
        if (!$this->conn) {
            $this->connect();
        }
        return $this->conn;
    }

    /**
     * Handle connection errors gracefully
     */
    private function handleConnectionError($e)
    {
        error_log("Database Connection Error: " . $e->getMessage());

        if ($this->isDevelopment()) {
            $this->showDevelopmentError($e);
        } else {
            $this->showProductionError();
        }
    }

    /**
     * Check if we're in development environment
     */
    private function isDevelopment()
    {
        return ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');
    }

    /**
     * Show detailed error for development
     */
    private function showDevelopmentError($e)
    {
        echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; font-family: sans-serif; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">';
        echo '<h3>Database Connection Error</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>Quick Fix:</strong> Verify your database credentials in <code>config/database.php</code> and ensure XAMPP MySQL is running.</p>';
        echo '</div>';
        exit;
    }

    /**
     * Show user-friendly error for production
     */
    private function showProductionError()
    {
        header('HTTP/1.1 503 Service Unavailable');
        echo '<div style="text-align: center; padding: 50px; font-family: sans-serif;">';
        echo '<h1>Service Temporarily Unavailable</h1>';
        echo '<p>We are currently experiencing technical difficulties. Please try again later.</p>';
        echo '</div>';
        exit;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {}
}

// Helper function for global access
function db()
{
    return Database::getInstance();
}
