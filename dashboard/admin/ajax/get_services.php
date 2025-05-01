<?php
// Fix database connection include path
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set headers
header('Content-Type: application/json');

try {
    // Fetch all service configurations with related information
    $query = "
        SELECT 
            vsc.id,
            c.name AS country_name,
            vt.name AS visa_type_name,
            st.name AS service_type_name,
            cm.name AS consultation_mode_name,
            vsc.price,
            vsc.is_active
        FROM visa_service_configurations vsc
        JOIN visa_types vt ON vsc.visa_type_id = vt.id
        JOIN countries c ON vt.country_id = c.id
        JOIN service_types st ON vsc.service_type_id = st.id
        JOIN consultation_modes cm ON vsc.consultation_mode_id = cm.id
        ORDER BY c.name, vt.name, st.name
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $services = [];
    
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    
    // Log the count of records for debugging
    error_log("Fetched " . count($services) . " service configurations");
    
    echo json_encode($services);
    
} catch (Exception $e) {
    error_log("Error in get_services.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
