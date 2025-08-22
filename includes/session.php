<?php
// includes/session.php
// Session management and authentication helpers

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Check if user is admin
function isAdmin() {
    return hasRole('admin');
}

// Check if user is trader
function isTrader() {
    return hasRole('trader');
}

// Check if user is customer
function isCustomer() {
    return hasRole('customer');
}

// Get current user ID
function getUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role'],
        'status' => $_SESSION['status']
    ];
}

// Login user
function loginUser($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['status'] = $user['status'];
    
    // Initialize guest cart merge if needed
    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
        $_SESSION['merge_cart'] = true;
    }
}

// Logout user
function logoutUser() {
    // Keep guest cart if exists
    $guestCart = isset($_SESSION['guest_cart']) ? $_SESSION['guest_cart'] : null;
    
    session_destroy();
    session_start();
    
    // Restore guest cart
    if ($guestCart) {
        $_SESSION['guest_cart'] = $guestCart;
    }
}

// Redirect based on role
function redirectByRole() {
    if (!isLoggedIn()) {
        header('Location: /shopfusion/auth/login.php');
        exit();
    }
    
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location:/shopfusion/admin/index.php');
            break;
        case 'trader':
            if ($_SESSION['status'] === 'pending') {
                header('Location: /shopfusion/auth/pending.php');
            } else {
                header('Location: /shopfusion/trader/');
            }
            break;
        case 'customer':
            header('Location: /shopfusion/');
            break;
        default:
            header('Location: /shopfusion/');
    }
    exit();
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /shopfusion/auth/login.php');
        exit();
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: /shopfusion/');
        exit();
    }
}

// Require admin
function requireAdmin() {
    requireRole('admin');
}

// Require trader
function requireTrader() {
    requireRole('trader');
}

// Check if account is active
function checkAccountStatus() {
    if (isLoggedIn() && $_SESSION['status'] === 'disabled') {
        logoutUser();
        $_SESSION['error'] = 'Your account has been disabled. Please contact administrator.';
        header('Location: /shopfusion/auth/login.php');
        exit();
    }
}

// Guest cart functions
function addToGuestCart($productId, $quantity = 1) {
    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }
    
    if (isset($_SESSION['guest_cart'][$productId])) {
        $_SESSION['guest_cart'][$productId] += $quantity;
    } else {
        $_SESSION['guest_cart'][$productId] = $quantity;
    }
}

function getGuestCart() {
    return isset($_SESSION['guest_cart']) ? $_SESSION['guest_cart'] : [];
}

function removeFromGuestCart($productId) {
    if (isset($_SESSION['guest_cart'][$productId])) {
        unset($_SESSION['guest_cart'][$productId]);
    }
}

function clearGuestCart() {
    unset($_SESSION['guest_cart']);
}

function getGuestCartCount() {
    if (!isset($_SESSION['guest_cart'])) return 0;
    return array_sum($_SESSION['guest_cart']);
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function hasFlashMessage($type) {
    return isset($_SESSION['flash'][$type]);
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Auto-logout on status change
checkAccountStatus();
?>