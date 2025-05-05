<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a team member
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'member') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Verify required tables exist
$tables_check = $conn->query("
    SELECT 
        COUNT(*) AS procedures_exist FROM information_schema.routines 
        WHERE routine_schema = DATABASE() 
        AND routine_name = 'check_team_member_availability'
");
$result = $tables_check->fetch_assoc();
if ($result['procedures_exist'] == 0) {
    echo json_encode(['error' => 'Availability system is being set up. Please try again later.']);
    exit;
}

// Get request data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['date']) || !isset($data['time']) || !isset($data['duration'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$date = $data['date'];
$time = $data['time'];
$duration = intval($data['duration']);
$datetime_string = $date . ' ' . $time;

// Calculate end time
$start_datetime = new DateTime($datetime_string);
$end_datetime = clone $start_datetime;
$end_datetime->add(new DateInterval('PT' . $duration . 'M'));

$start = $start_datetime->format('Y-m-d H:i:s');
$end = $end_datetime->format('Y-m-d H:i:s');

try {
    // Get team member ID from the team_members table based on the logged-in user
    $query = "SELECT id FROM team_members WHERE user_id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Team member record not found']);
        exit;
    }
    
    $team_member = $result->fetch_assoc();
    $team_member_id = $team_member['id'];
    $stmt->close();
    
    // Check availability using the stored procedure
    $stmt = $conn->prepare("CALL check_team_member_availability(?, ?, ?, @is_available)");
    $stmt->bind_param("iss", $team_member_id, $start, $end);
    $stmt->execute();
    $stmt->close();
    
    $result = $conn->query("SELECT @is_available AS is_available");
    $availability = $result->fetch_assoc();
    
    echo json_encode(['is_available' => (bool)$availability['is_available']]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
