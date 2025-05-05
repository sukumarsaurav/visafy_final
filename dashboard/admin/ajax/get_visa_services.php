<?php
// Include database connection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../config/db_connect.php';
// Check if visa_id is provided
if (!isset($_GET['visa_id']) || empty($_GET['visa_id'])) {
    echo json_encode([]);
    exit;
}

$visa_id = intval($_GET['visa_id']);

// Get active services for the selected visa
$query = "SELECT vs.visa_service_id, st.service_name, vs.base_price 
          FROM visa_services vs 
          JOIN service_types st ON vs.service_type_id = st.service_type_id 
          WHERE vs.visa_id = ? AND vs.is_active = 1 AND st.is_active = 1 
          ORDER BY st.service_name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$result = $stmt->get_result();
$services = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}
$stmt->close();

// Return services as JSON
header('Content-Type: application/json');
echo json_encode($services);
