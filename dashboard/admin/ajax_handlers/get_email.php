<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user has permission to access this page
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check required parameters
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['type'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$id = (int)$_GET['id'];
$type = $_GET['type'];

// Get the email based on type
try {
    // Check if cc_emails and bcc_emails columns exist in the email_queue table
    $check_columns = $conn->query("SHOW COLUMNS FROM email_queue LIKE 'cc_emails'");
    $cc_column_exists = $check_columns->num_rows > 0;
    
    if ($type === 'sent') {
        // Get sent/queued email
        if ($cc_column_exists) {
            $stmt = $conn->prepare("
                SELECT eq.*, CONCAT(u.first_name, ' ', u.last_name) as recipient_name
                FROM email_queue eq
                LEFT JOIN users u ON eq.recipient_id = u.id
                WHERE eq.id = ?
            ");
        } else {
            // If columns don't exist, use a query without them
            $stmt = $conn->prepare("
                SELECT eq.id, eq.recipient_id, eq.recipient_email, eq.subject, 
                       eq.content, eq.status, eq.scheduled_time, eq.sent_at, 
                       eq.created_by, eq.created_at, 
                       NULL as cc_emails, NULL as bcc_emails,
                       CONCAT(u.first_name, ' ', u.last_name) as recipient_name
                FROM email_queue eq
                LEFT JOIN users u ON eq.recipient_id = u.id
                WHERE eq.id = ?
            ");
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Email not found']);
            exit();
        }
        
        $email = $result->fetch_assoc();
        
        // Send response
        echo json_encode([
            'success' => true,
            'email' => $email
        ]);
    } else if ($type === 'received') {
        // Get received email
        $stmt = $conn->prepare("
            SELECT re.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name
            FROM received_emails re
            LEFT JOIN users u ON re.sender_id = u.id
            WHERE re.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Email not found']);
            exit();
        }
        
        $email = $result->fetch_assoc();
        
        // Mark as read if not already
        if (!$email['is_read']) {
            $updateStmt = $conn->prepare("
                UPDATE received_emails 
                SET is_read = 1, read_by = ?, read_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->bind_param("ii", $_SESSION['id'], $id);
            $updateStmt->execute();
        }
        
        // Send response
        echo json_encode([
            'success' => true,
            'email' => $email
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email type']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 