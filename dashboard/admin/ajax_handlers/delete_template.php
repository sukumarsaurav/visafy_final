<?php
session_start();
require_once '../../../config/db_connect.php';

// Check if user has permission to access this page
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if delete_template flag is set
if (!isset($_POST['delete_template']) || $_POST['delete_template'] !== 'true') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Check if template_id is provided
if (!isset($_POST['template_id']) || !is_numeric($_POST['template_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
    exit();
}

$template_id = (int)$_POST['template_id'];

// Delete the template
$stmt = $conn->prepare("DELETE FROM email_templates WHERE id = ?");
$stmt->bind_param("i", $template_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting template: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?> 