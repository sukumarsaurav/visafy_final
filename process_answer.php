<?php
include_once 'includes/init.php';

// Redirect if not logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assessment_id = intval($_POST['assessment_id'] ?? 0);
    $question_id = intval($_POST['question_id'] ?? 0);
    $option_id = intval($_POST['option_id'] ?? 0);
    
    // Validate data
    if (!$assessment_id || !$question_id || !$option_id) {
        $_SESSION['error'] = 'Invalid form data';
        header('Location: eligibility-check.php');
        exit();
    }
    
    // Check if this assessment belongs to the current user
    $stmt = $conn->prepare("SELECT * FROM user_assessments WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $assessment_id, $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = 'Invalid assessment';
        header('Location: eligibility-check.php');
        exit();
    }
    $stmt->close();
    
    // Record the answer
    $stmt = $conn->prepare("INSERT INTO user_assessment_answers 
                         (assessment_id, question_id, option_id) 
                         VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $assessment_id, $question_id, $option_id);
    $stmt->execute();
    $stmt->close();
    
    // Increment question counter for UI
    if (!isset($_SESSION['question_number'])) {
        $_SESSION['question_number'] = 1;
    }
    $_SESSION['question_number']++;
    
    // Redirect back to eligibility-check.php to show next question
    header('Location: eligibility-check.php');
    exit();
} else {
    // Not a POST request
    header('Location: eligibility-check.php');
    exit();
}
