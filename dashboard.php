<?php
require_once 'config/session.php';
require_once 'config/auth.php';

$session = new SessionManager();
$auth = new Authentication();

// Check if user is logged in
if (!$session->isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Redirect based on user type
$userType = $session->get('user_type', 'student');
header("Location: {$userType}/dashboard.php");
exit;
?>