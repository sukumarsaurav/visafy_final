<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../config/db_connect.php';

// Get service ID from request
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

$consultation_modes = [];

if ($service_id > 0) {
    // Query to get consultation modes for the selected service
    $query = "SELECT scm.service_consultation_id, cm.mode_name, scm.additional_fee, scm.duration_minutes
              FROM service_consultation_modes scm
              JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
              WHERE scm.visa_service_id = ? AND scm.is_available = 1 AND cm.is_active = 1
              ORDER BY cm.mode_name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $consultation_modes[] = [
                'service_consultation_id' => $row['service_consultation_id'],
                'mode_name' => $row['mode_name'],
                'additional_fee' => $row['additional_fee'],
                'duration_minutes' => $row['duration_minutes']
            ];
        }
    }
    
    $stmt->close();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($consultation_modes);
