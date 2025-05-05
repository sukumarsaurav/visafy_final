<?php
// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../config/db_connect.php';
require_once '../../../includes/functions.php';

// Check if user is logged in as admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['option_id']) && is_numeric($_POST['option_id'])) {
    $option_id = intval($_POST['option_id']);
    
    // Delete the option
    $stmt = $conn->prepare("DELETE FROM decision_tree_options WHERE id = ?");
    $stmt->bind_param("i", $option_id);
    
    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Option deleted successfully'];
    } else {
        $response['message'] = 'Error deleting option: ' . $stmt->error;
    }
    $stmt->close();
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);
