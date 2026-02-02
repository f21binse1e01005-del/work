<?php
// assets/favicon.ico.php
$iconPath = __DIR__ . '/16x16.svg';

if (file_exists($iconPath)) {
    header('Content-Type: image/svg+xml');
    readfile($iconPath);
} else {
    // Create a simple gradient icon
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="64" height="64" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#4361ee"/>
                <stop offset="100%" stop-color="#3a0ca3"/>
            </linearGradient>
        </defs>
        <rect width="64" height="64" rx="12" fill="url(#grad)"/>
        <text x="32" y="38" text-anchor="middle" fill="white" font-family="Arial" font-size="24" font-weight="bold">S</text>
        <text x="32" y="48" text-anchor="middle" fill="white" font-family="Arial" font-size="12">WAY</text>
    </svg>';
}
?>