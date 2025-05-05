<?php
include_once 'includes/init.php';

// Redirect if not logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit();
}

// Reset assessment session
if (isset($_SESSION['assessment_id'])) {
    unset($_SESSION['assessment_id']);
}

if (isset($_SESSION['question_number'])) {
    unset($_SESSION['question_number']);
}

// Redirect to eligibility check page
header('Location: eligibility-check.php');
exit();
