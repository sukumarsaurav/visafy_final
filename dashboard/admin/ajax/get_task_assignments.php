<?php
require_once '../../../config/db_connect.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/session.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['id']) || $_SESSION['user_type'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Check if task_id is provided
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid task ID'
    ]);
    exit;
}

$task_id = intval($_GET['task_id']);

// Get assignments for the task
$query = "SELECT team_member_id FROM task_assignments WHERE task_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $task_id);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row['team_member_id'];
}

// Return assignments
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'assignments' => $assignments
]);
exit; 