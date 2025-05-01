<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$team_member_id = isset($_POST['team_member_id']) ? intval($_POST['team_member_id']) : null;
$day_of_week = isset($_POST['day_of_week']) ? intval($_POST['day_of_week']) : null;
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : null;
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : null;
$slot_duration = isset($_POST['slot_duration']) ? intval($_POST['slot_duration']) : 60;
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Validate required fields
if (!$team_member_id || !is_numeric($day_of_week) || !$start_time || !$end_time) {
    echo json_encode([
        'success' => false, 
        'message' => 'All fields are required'
    ]);
    exit;
}

// Validate day of week
if ($day_of_week < 0 || $day_of_week > 6) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid day of week'
    ]);
    exit;
}

// Validate time format
if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $start_time) || 
    !preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $end_time)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid time format'
    ]);
    exit;
}

// Add seconds to times for database format
$start_time = $start_time . ':00';
$end_time = $end_time . ':00';

// Validate start time is before end time
if ($start_time >= $end_time) {
    echo json_encode([
        'success' => false, 
        'message' => 'Start time must be before end time'
    ]);
    exit;
}

// Check if team member exists and belongs to the current admin
$check_team_sql = "
    SELECT tm.id 
    FROM team_members tm 
    WHERE tm.id = ? AND tm.deleted_at IS NULL
";
$stmt = $conn->prepare($check_team_sql);
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid team member'
    ]);
    exit;
}

// Check for overlapping timeslots for this team member
$check_overlap_sql = "
    SELECT id 
    FROM team_member_timeslots 
    WHERE team_member_id = ? 
    AND day_of_week = ? 
    AND (
        (start_time <= ? AND end_time > ?) OR 
        (start_time < ? AND end_time >= ?) OR 
        (start_time >= ? AND end_time <= ?)
    )
";
$stmt = $conn->prepare($check_overlap_sql);
$stmt->bind_param("iissssss", 
    $team_member_id, 
    $day_of_week, 
    $end_time, 
    $start_time, 
    $end_time, 
    $start_time, 
    $start_time, 
    $end_time
);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'This timeslot overlaps with an existing timeslot for this team member'
    ]);
    exit;
}

// Insert the new timeslot
$insert_sql = "
    INSERT INTO team_member_timeslots 
    (team_member_id, day_of_week, start_time, end_time, slot_duration, is_active) 
    VALUES (?, ?, ?, ?, ?, ?)
";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("iissii", 
    $team_member_id, 
    $day_of_week, 
    $start_time, 
    $end_time, 
    $slot_duration, 
    $is_active
);

if ($stmt->execute()) {
    $timeslot_id = $conn->insert_id;
    echo json_encode([
        'success' => true, 
        'message' => 'Timeslot added successfully',
        'timeslot_id' => $timeslot_id
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to add timeslot: ' . $conn->error
    ]);
} 