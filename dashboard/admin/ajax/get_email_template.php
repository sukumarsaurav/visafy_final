<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $template_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $template = $result->fetch_assoc();
        echo json_encode(['success' => true, 'template' => $template]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Template not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid template ID']);
}
?> 