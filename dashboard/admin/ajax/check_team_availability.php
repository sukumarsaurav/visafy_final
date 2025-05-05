<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if required parameters are provided
if (!isset($data['date']) || !isset($data['time']) || !isset($data['duration'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$date = $data['date'];
$time = $data['time'];
$duration = intval($data['duration']);

// Combine date and time
$booking_datetime = $date . ' ' . $time . ':00';
// Calculate end time
$end_datetime = date('Y-m-d H:i:s', strtotime($booking_datetime . " +$duration minutes"));

// Get all team members
$query = "SELECT tm.id as team_member_id, tm.role, tm.custom_role_name, 
          u.id as user_id, u.first_name, u.last_name, u.email,
          CONCAT(u.first_name, ' ', u.last_name) AS team_member_name 
          FROM team_members tm
          JOIN users u ON tm.user_id = u.id
          WHERE tm.deleted_at IS NULL AND u.status = 'active'
          ORDER BY u.first_name, u.last_name";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$team_members = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $team_members[] = $row;
    }
}
$stmt->close();

// Check availability for each team member
$available_members = [];

foreach ($team_members as $member) {
    // Call the stored procedure to check availability
    $is_available = false;
    
    $check_query = "CALL check_team_member_availability(?, ?, ?, @is_available)";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('iss', $member['team_member_id'], $booking_datetime, $end_datetime);
    $stmt->execute();
    $stmt->close();
    
    // Get the output parameter
    $result = $conn->query("SELECT @is_available AS is_available");
    if ($result && $row = $result->fetch_assoc()) {
        $is_available = (bool)$row['is_available'];
    }
    
    // Add availability status to the member data
    $member['is_available'] = $is_available;
    $available_members[] = $member;
}

// Sort by availability (available first)
usort($available_members, function($a, $b) {
    if ($a['is_available'] === $b['is_available']) {
        return 0;
    }
    return $a['is_available'] ? -1 : 1;
});

// Return team members with availability status as JSON
header('Content-Type: application/json');
echo json_encode($available_members);
