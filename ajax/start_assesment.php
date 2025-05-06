<?php
require_once '../config/db_connect.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Get user ID from session or use a temporary ID for guests
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Create new assessment
    $stmt = $conn->prepare("INSERT INTO user_assessments (user_id, start_time) VALUES (?, NOW())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $assessment_id = $conn->insert_id;
    
    echo json_encode([
        'success' => true,
        'assessment_id' => $assessment_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error starting assessment: ' . $e->getMessage()
    ]);
}
?>
