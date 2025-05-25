<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login if not
function requireLogin() {
    if (!isLoggedIn()) {
        // Store the requested URL for redirection after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: /admin/login.php');
        exit;
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Login user
function loginUser($user_id, $username, $role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['logged_in_time'] = time();
}

// Logout user
function logoutUser() {
    session_unset();
    session_destroy();
}

// Check if current page is an admin page and require login
$current_uri = $_SERVER['REQUEST_URI'];
if (strpos($current_uri, '/admin/') !== false && 
    strpos($current_uri, '/admin/login.php') === false &&
    strpos($current_uri, '/admin/logout.php') === false) {
    requireLogin();
}

// Check for session timeout (auto logout after 2 hours of inactivity)
if (isLoggedIn() && isset($_SESSION['logged_in_time'])) {
    $timeout_duration = 2 * 60 * 60; // 2 hours in seconds
    $current_time = time();
    
    if ($current_time - $_SESSION['logged_in_time'] > $timeout_duration) {
        // Session has expired, log the user out
        logoutUser();
        
        // Store the requested URL for redirection after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        $_SESSION['timeout_message'] = 'Your session has expired. Please log in again.';
        
        // Redirect to login page
        header('Location: /admin/login.php');
        exit;
    } else {
        // Update the last activity time for the user
        $_SESSION['logged_in_time'] = $current_time;
    }
}

// REMOVE THIS TEMPORARY CODE - it was automatically logging everyone in as admin
// This was the security hole in your system
/*
if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false && !isset($_SESSION['user_id'])) {
    // Auto-login as admin for admin pages
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['user_role'] = 'admin';
    $_SESSION['logged_in_time'] = time();
}
*/
?>