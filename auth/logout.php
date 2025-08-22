<?php
// auth/logout.php
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Clear all session data
$_SESSION = [];

// Destroy session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Remove "remember me" cookie if set
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Set a flash message
setFlashMessage('success', 'You have been logged out successfully.');

// Redirect to login page
header('Location: login.php');
exit();
