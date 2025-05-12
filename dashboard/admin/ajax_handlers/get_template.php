<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user has permission to access this page
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if template_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
    exit();
}

$template_id = (int)$_GET['id'];

// Get the template details
$stmt = $conn->prepare("SELECT id, name, subject, content, template_type FROM email_templates WHERE id = ?");
$stmt->bind_param("i", $template_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Template not found']);
} else {
    $template = $result->fetch_assoc();
    echo json_encode(['success' => true, 'template' => $template]);
}

$stmt->close();
$conn->close();
?> 