<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user has permission to access this page
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if email_id is provided
if (!isset($_POST['email_id']) || !is_numeric($_POST['email_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email ID']);
    exit();
}

$email_id = (int)$_POST['email_id'];

try {
    // Update the scheduled time to NOW() and status to 'processing'
    $stmt = $conn->prepare("UPDATE email_queue SET scheduled_time = NOW(), status = 'processing' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $email_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Log this action
        // Check if activity_logs table exists before trying to log
        $table_check = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($table_check->num_rows > 0) {
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, 'send_now', 'email', ?, 'Email sent immediately', ?)");
            $log_stmt->bind_param("iis", $_SESSION['id'], $email_id, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found or already being processed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 