<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if required parameters are provided
if (!isset($data['member_id']) || !isset($data['booking_datetime']) || !isset($data['duration'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$member_id = intval($data['member_id']);
$booking_datetime = $data['booking_datetime'];
$duration = intval($data['duration']);

// Convert booking_datetime from format YYYY-MM-DDThh:mm to MySQL datetime
$booking_datetime = date('Y-m-d H:i:s', strtotime($booking_datetime));
// Calculate end time
$end_datetime = date('Y-m-d H:i:s', strtotime($booking_datetime . " +$duration minutes"));

// Use stored procedure to check availability
$is_available = false;

// Call the stored procedure
$query = "CALL check_team_member_availability(?, ?, ?, @is_available)";
$stmt = $conn->prepare($query);
$stmt->bind_param('iss', $member_id, $booking_datetime, $end_datetime);
$stmt->execute();
$stmt->close();

// Get the output parameter
$result = $conn->query("SELECT @is_available AS is_available");
if ($result && $row = $result->fetch_assoc()) {
    $is_available = (bool)$row['is_available'];
}

// Return availability status as JSON
header('Content-Type: application/json');
echo json_encode(['available' => $is_available]);
