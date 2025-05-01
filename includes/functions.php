<?php
// Start session only if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is logged in
 *
 * @return bool True if user is logged in, false otherwise
 */
function is_user_logged_in() {
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

/**
 * Check if logged-in user is an admin
 *
 * @return bool True if user is logged in and is an admin, false otherwise
 */
function is_admin_logged_in() {
    return is_user_logged_in() && isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "admin";
}

/**
 * Check if logged-in user is a team member
 *
 * @return bool True if user is logged in and is a team member, false otherwise
 */
function is_team_member_logged_in() {
    return is_user_logged_in() && isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "member";
}

/**
 * Check if logged-in user is an applicant
 *
 * @return bool True if user is logged in and is an applicant, false otherwise
 */
function is_applicant_logged_in() {
    return is_user_logged_in() && isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "applicant";
}

/**
 * Generate a random string
 *
 * @param int $length Length of the random string
 * @return string Random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

/**
 * Generate a unique reference number for various entities
 *
 * @param string $prefix Prefix for the reference number
 * @return string Unique reference number
 */
function generate_reference_number($prefix = 'REF') {
    $timestamp = time();
    $random = generate_random_string(6);
    return $prefix . $timestamp . $random;
}

/**
 * Sanitize user input
 *
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirect to a URL
 *
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if an email is valid
 *
 * @param string $email Email to check
 * @return bool True if email is valid, false otherwise
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format a date
 *
 * @param string $date Date to format
 * @param string $format Format to use
 * @return string Formatted date
 */
function format_date($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Format a datetime
 *
 * @param string $datetime Datetime to format
 * @param string $format Format to use
 * @return string Formatted datetime
 */
function format_datetime($datetime, $format = 'M d, Y h:i A') {
    return date($format, strtotime($datetime));
}
