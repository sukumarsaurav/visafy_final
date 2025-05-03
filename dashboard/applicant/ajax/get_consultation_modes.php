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
    $query = "SELECT cm.id as consultation_mode_id, cm.name as mode_name, 
              cm.description, cm.duration_minutes, 
              COALESCE(vscm.price, cm.base_price) as base_price,
              COALESCE(vscm.total_price, cm.base_price) as total_price
              FROM consultation_modes cm
              LEFT JOIN visa_service_consultation_modes vscm 
                ON cm.id = vscm.consultation_mode_id AND vscm.visa_service_id = ?
              WHERE cm.is_active = 1
              AND (vscm.visa_service_id = ? OR vscm.visa_service_id IS NULL)
              ORDER BY vscm.visa_service_id DESC, cm.name ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $serviceId, $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $modes = [];
    while ($row = $result->fetch_assoc()) {
        // Avoid duplicate modes (prefer service-specific pricing)
        $modeExists = false;
        foreach ($modes as $existingMode) {
            if ($existingMode['consultation_mode_id'] == $row['consultation_mode_id']) {
                $modeExists = true;
                break;
            }
        }
        
        if (!$modeExists) {
            $modes[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'modes' => $modes
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching consultation modes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching consultation modes'
    ]);
}
?>
