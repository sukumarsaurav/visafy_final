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

// Set headers
header('Content-Type: application/json');

// Get service id
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit;
}

// Fetch service details
$query = "
    SELECT vsc.*, c.id AS country_id
    FROM visa_service_configurations vsc
    JOIN visa_types vt ON vsc.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id
    WHERE vsc.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Service not found']);
    exit;
}

$service = $result->fetch_assoc();
echo json_encode($service);
