<?php
// Fix database connection include path
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate input
$id = isset($_POST['id']) ? intval($_POST['id']) : null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service configuration ID']);
    exit;
}

// Check if the configuration exists
$check_query = "SELECT id FROM visa_service_configurations WHERE id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Service configuration not found']);
    exit;
}

// Check if the configuration is being used in any applications
$check_usage_query = "
    SELECT id FROM visa_applications 
    WHERE visa_type_id IN (
        SELECT visa_type_id FROM visa_service_configurations WHERE id = ?
    )
    AND service_type_id IN (
        SELECT service_type_id FROM visa_service_configurations WHERE id = ?
    )
    AND consultation_mode_id IN (
        SELECT consultation_mode_id FROM visa_service_configurations WHERE id = ?
    )
    LIMIT 1
";
$stmt = $conn->prepare($check_usage_query);
$stmt->bind_param("iii", $id, $id, $id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'This service configuration is in use and cannot be deleted'
    ]);
    exit;
}

// Delete the configuration
$delete_query = "DELETE FROM visa_service_configurations WHERE id = ?";
$stmt = $conn->prepare($delete_query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Service configuration deleted successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete service configuration: ' . $stmt->error
    ]);
}
