<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Get visa ID from request
$visa_id = isset($_GET['visa_id']) ? intval($_GET['visa_id']) : 0;

$services = [];

if ($visa_id > 0) {
    // Query to get service types for the selected visa
    $query = "SELECT vs.visa_service_id, st.service_name, vs.base_price, vs.description
              FROM visa_services vs
              JOIN service_types st ON vs.service_type_id = st.service_type_id
              WHERE vs.visa_id = ? AND vs.is_active = 1
              ORDER BY st.service_name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $visa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $services[] = [
                'visa_service_id' => $row['visa_service_id'],
                'service_name' => $row['service_name'] . ' ($' . number_format($row['base_price'], 2) . ')',
                'description' => $row['description']
            ];
        }
    }
    
    $stmt->close();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($services);
?>
