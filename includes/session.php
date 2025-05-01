<?php
// Start session only if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set session timeout to 30 minutes (1800 seconds)
$session_timeout = 1800;

// Check if the user's last activity is set
if (isset($_SESSION['last_activity'])) {
    // Calculate the time passed since the user's last activity
    $time_passed = time() - $_SESSION['last_activity'];
    
    // If more time has passed than the session timeout, destroy the session
    if ($time_passed > $session_timeout) {
        session_unset();
        session_destroy();
        
        // Redirect to login page if this is accessed directly
        if (basename($_SERVER['PHP_SELF']) != 'login.php' && 
            basename($_SERVER['PHP_SELF']) != 'index.php' && 
            basename($_SERVER['PHP_SELF']) != 'logout.php') {
            header("Location: /login.php?session_expired=1");
            exit();
        }
    }
}

// Update the last activity time
$_SESSION['last_activity'] = time();

// Set session cookie parameters
$current_cookie_params = session_get_cookie_params();
session_set_cookie_params(
    $session_timeout,
    $current_cookie_params['path'],
    $current_cookie_params['domain'],
    isset($_SERVER['HTTPS']), // Secure flag based on HTTPS
    true // HttpOnly flag
);

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
} else if (time() - $_SESSION['created_at'] > 600) { // Regenerate every 10 minutes
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}

// Check if the user is logged in and authorize access
function authorize_admin() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'admin') {
        header('Location: /login.php');
        exit();
    }
}

function authorize_team_member() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'member') {
        header('Location: /login.php');
        exit();
    }
}

function authorize_applicant() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'applicant') {
        header('Location: /login.php');
        exit();
    }
}

function authorize_any_user() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: /login.php');
        exit();
    }
} 