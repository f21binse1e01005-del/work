<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing admin dashboard components...<br>";

try {
    echo "1. Testing database connection...<br>";
    require_once '../config/database.php';
    $db = (new Database())->getConnection();
    if ($db) {
        echo "✓ Database connected<br>";
    } else {
        echo "✗ Database connection failed<br>";
    }
    
    echo "2. Testing session...<br>";
    require_once '../config/session.php';
    $session = new SessionManager();
    echo "✓ Session manager loaded<br>";
    
    echo "3. Testing auth...<br>";
    require_once '../config/auth.php';
    $auth = new Authentication();
    echo "✓ Authentication loaded<br>";
    
    echo "4. Testing admin header...<br>";
    // Simulate admin session
    $session->set('user_id', 1);
    $session->set('user_type', 'admin');
    $session->set('full_name', 'Test Admin');
    
    // Test the header file
    ob_start();
    include 'includes/header.php';
    $headerOutput = ob_get_clean();
    
    if (strlen($headerOutput) > 100) {
        echo "✓ Header loaded successfully<br>";
    } else {
        echo "✗ Header failed to load<br>";
        echo "Header output: " . htmlspecialchars($headerOutput) . "<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}
?>