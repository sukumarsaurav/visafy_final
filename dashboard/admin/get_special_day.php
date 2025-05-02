<?php
// Include database connection
require_once '../../config/db_connect.php';

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

// Get special day ID from GET parameter
$special_day_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate special day ID
if ($special_day_id <= 0) {
    echo json_encode(['error' => 'Valid special day ID is required']);
    exit;
}

try {
    // Get special day data
    $stmt = $conn->prepare("SELECT * FROM special_days WHERE id = ?");
    $stmt->bind_param('i', $special_day_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Special day not found']);
        exit;
    }
    
    $special_day = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode($special_day);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?> 