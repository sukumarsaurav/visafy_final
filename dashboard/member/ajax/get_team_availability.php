<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a team member
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'member') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Verify if tables exist before querying
$tables_exist = true;
$tables_check = $conn->query("
    SELECT 
        COUNT(*) AS timeslots_exists FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'team_member_timeslots'
");
$result = $tables_check->fetch_assoc();
if ($result['timeslots_exists'] == 0) {
    echo json_encode(['error' => 'Availability system is being set up. Please try again later.']);
    exit;
}

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

try {
    // Get availability
    $availability_query = "SELECT * FROM team_member_timeslots 
                          WHERE team_member_id = ? AND is_active = 1
                          ORDER BY day_of_week, start_time";
    
    $stmt = $conn->prepare($availability_query);
    $stmt->bind_param("i", $team_member_id);
    $stmt->execute();
    $availability_result = $stmt->get_result();
    $availability = [];
    
    while ($row = $availability_result->fetch_assoc()) {
        $availability[] = $row;
    }
    $stmt->close();
    
    // Get time off
    $timeoff_query = "SELECT * FROM team_member_time_off 
                     WHERE team_member_id = ? AND status = 'approved' AND end_datetime >= NOW()
                     ORDER BY start_datetime";
    
    $stmt = $conn->prepare($timeoff_query);
    $stmt->bind_param("i", $team_member_id);
    $stmt->execute();
    $timeoff_result = $stmt->get_result();
    $time_off = [];
    
    while ($row = $timeoff_result->fetch_assoc()) {
        $time_off[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'availability' => $availability,
        'time_off' => $time_off
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
