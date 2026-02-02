<?php
/**
 * Skills Way Vocational Institute - Logout Handler
 * File: logout.php
 * Description: Secure session termination
 */

session_start();

// Destroy all session data
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Redirect to login page
header('Location: login.php');
exit();
?>