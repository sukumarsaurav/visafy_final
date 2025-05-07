<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../../config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'applicant') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Validate service_id
if (!isset($_GET['service_id']) || !is_numeric($_GET['service_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid service ID'
    ]);
    exit;
}

$serviceId = intval($_GET['service_id']);

try {
    // Query to get consultation modes with pricing for the selected service
    $query = "SELECT cm.consultation_mode_id, cm.mode_name, 
              cm.description, scm.duration_minutes, 
              vs.base_price,
              (vs.base_price + IFNULL(scm.additional_fee, 0)) as total_price
              FROM consultation_modes cm
              JOIN service_consultation_modes scm 
                ON cm.consultation_mode_id = scm.consultation_mode_id
              JOIN visa_services vs
                ON scm.visa_service_id = vs.visa_service_id
              WHERE cm.is_active = 1
              AND scm.visa_service_id = ?
              AND scm.is_available = 1
              ORDER BY cm.mode_name ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $modes = [];
    while ($row = $result->fetch_assoc()) {
        $modes[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'modes' => $modes
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching consultation modes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching consultation modes: ' . $e->getMessage()
    ]);
}
?>
