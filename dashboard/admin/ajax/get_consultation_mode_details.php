<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Check if consultation mode ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'No consultation mode ID provided']);
    exit;
}

$consultation_id = intval($_GET['id']);

// Get consultation mode details
$query = "SELECT scm.service_consultation_id, cm.mode_name, scm.additional_fee, scm.duration_minutes 
          FROM service_consultation_modes scm 
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id 
          WHERE scm.service_consultation_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $consultation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $mode_details = $result->fetch_assoc();
} else {
    $mode_details = ['error' => 'Consultation mode not found'];
}
$stmt->close();

// Return consultation mode details as JSON
header('Content-Type: application/json');
echo json_encode($mode_details);
