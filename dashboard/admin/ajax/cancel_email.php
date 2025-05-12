<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $email_id = (int)$_POST['id'];
    
    // Only allow cancellation of pending emails
    $stmt = $conn->prepare("DELETE FROM email_queue WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $email_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Email could not be cancelled or has already been processed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid email ID']);
}
?> 