<?php
// admin/update-theme.php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit();
}

if (isset($_POST['theme']) && in_array($_POST['theme'], ['light', 'dark'])) {
    $_SESSION['theme'] = $_POST['theme'];
    echo json_encode(['status' => 'success', 'theme' => $_POST['theme']]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid theme']);
}
?>