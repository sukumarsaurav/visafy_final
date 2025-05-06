<?php
// Only start session if one isn't already active
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $option_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT * FROM decision_tree_options WHERE id = ?");
    $stmt->bind_param("i", $option_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $option = $result->fetch_assoc();
        $response = $option;
        $response['success'] = true;
    } else {
        $response['message'] = 'Option not found';
    }
    $stmt->close();
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);
