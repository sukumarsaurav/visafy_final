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

// Get team members
$sql = "
    SELECT tm.id, u.name, tm.role, tm.custom_role_name
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    WHERE tm.deleted_at IS NULL
    ORDER BY u.name
";

$result = $conn->query($sql);

if ($result) {
    $team_members = [];
    while ($row = $result->fetch_assoc()) {
        // Add display role (custom role if set, otherwise standard role)
        $row['display_role'] = $row['custom_role_name'] ?: ucfirst($row['role']);
        $row['name'] = $row['name'] . ' (' . $row['display_role'] . ')';
        $team_members[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'team_members' => $team_members
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch team members: ' . $conn->error
    ]);
} 