<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['type']) && in_array($_GET['type'], ['sent', 'received'])) {
    $email_id = (int)$_GET['id'];
    $type = $_GET['type'];
    
    if ($type == 'sent') {
        $stmt = $conn->prepare("
            SELECT eq.*, CONCAT(u.first_name, ' ', u.last_name) AS recipient_name
            FROM email_queue eq
            LEFT JOIN users u ON eq.recipient_id = u.id
            WHERE eq.id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT re.*, CONCAT(u.first_name, ' ', u.last_name) AS sender_name
            FROM received_emails re
            LEFT JOIN users u ON re.sender_id = u.id
            WHERE re.id = ?
        ");
        
        // Mark as read
        $update_stmt = $conn->prepare("
            UPDATE received_emails 
            SET is_read = 1, read_by = ?, read_at = NOW() 
            WHERE id = ? AND is_read = 0
        ");
        $update_stmt->bind_param("ii", $_SESSION['id'], $email_id);
        $update_stmt->execute();
    }
    
    $stmt->bind_param("i", $email_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $email = $result->fetch_assoc();
        echo json_encode(['success' => true, 'email' => $email]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Email not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}
?> 