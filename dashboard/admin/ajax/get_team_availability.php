<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set response header
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get team member ID from GET parameter
$team_member_id = isset($_GET['team_member_id']) ? intval($_GET['team_member_id']) : 0;

// Validate team member ID
if ($team_member_id <= 0) {
    echo json_encode(['error' => 'Valid team member ID is required']);
    exit;
}

try {
    // Get team member availability
    $availability_query = "SELECT id, day_of_week, is_available, start_time, end_time
                         FROM team_member_availability 
                         WHERE team_member_id = ? AND is_available = 1
                         ORDER BY day_of_week";
    $stmt = $conn->prepare($availability_query);
    $stmt->bind_param('i', $team_member_id);
    $stmt->execute();
    $availability_result = $stmt->get_result();
    
    $availability = [];
    while ($row = $availability_result->fetch_assoc()) {
        $availability[] = $row;
    }
    $stmt->close();
    
    // Get upcoming time off
    $time_off_query = "SELECT id, start_datetime, end_datetime, reason, status
                      FROM team_member_time_off
                      WHERE team_member_id = ? 
                      AND end_datetime >= NOW()
                      ORDER BY start_datetime";
    $stmt = $conn->prepare($time_off_query);
    $stmt->bind_param('i', $team_member_id);
    $stmt->execute();
    $time_off_result = $stmt->get_result();
    
    $time_off = [];
    while ($row = $time_off_result->fetch_assoc()) {
        $time_off[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'availability' => $availability,
        'time_off' => $time_off
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?> 