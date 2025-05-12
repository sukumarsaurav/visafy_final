<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $template_id = (int)$_POST['id'];
    
    $stmt = $conn->prepare("DELETE FROM email_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error deleting template: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid template ID']);
}
?> 