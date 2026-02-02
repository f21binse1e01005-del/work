<?php

/**
 * Official Database Setup Script for Skills Way
 * Imports database/setup_xampp.sql directly
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "skills_way_vocational";

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup - Skills Way</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f8f9fa; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #bee5eb; }
        h1 { color: #2c3e50; margin-top: 0; }
        pre { background: #2d3436; color: #dfe6e9; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
    <h1>🚀 Skills Way Database Setup</h1>";

try {
    // 1. Connect to MySQL server
    $conn = new mysqli($host, $username, $password);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "<div class='success'>✅ Connected to MySQL server successfully!</div>";

    // 2. Read SQL file
    $sqlFile = __DIR__ . '/database/setup_xampp.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at: " . $sqlFile);
    }

    $sqlContent = file_get_contents($sqlFile);
    if (!$sqlContent) {
        throw new Exception("SQL file is empty or cannot be read.");
    }
    echo "<div class='info'>📄 Loaded schema file: setup_xampp.sql</div>";

    // 3. Execute SQL commands
    // Enable multi-query for batch execution
    // Simple splitting by semicolon might be fragile with complex triggers/procedures but works for standard dumps
    $conn->multi_query($sqlContent);

    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }

        // Prepare next result set
        if (!$conn->more_results()) {
            break;
        }
    } while ($conn->next_result());

    if ($conn->errno) {
        throw new Exception("SQL Execution Error: " . $conn->error);
    }

    echo "<div class='success'>✅ Database imported successfully!</div>";

    echo "<div class='info'>
        <h2>🎉 Setup Complete!</h2>
        <p>The database has been reset to the correct schema.</p>
        
        <h3>Login Credentials:</h3>
        <pre>
Admin:   admin.skills   / Admin123!
Teacher: teacher.demo   / Teacher123!
Student: student.demo   / Student123!
        </pre>
        
        <p><strong>Next Steps:</strong></p>
        <a href='index.php' class='btn'>Go to Homepage</a>
        <a href='login.php' class='btn'>Go to Login</a>
    </div>";

    $conn->close();
} catch (Exception $e) {
    echo "<div class='error'>❌ Setup Failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>
        <h3>Troubleshooting:</h3>
        <ul>
            <li>Ensure XAMPP MySQL is running</li>
            <li>Check if 'database/setup_xampp.sql' exists</li>
            <li>Manually import the SQL file via phpMyAdmin if this script fails</li>
        </ul>
    </div>";
}

echo "</div></body></html>";
