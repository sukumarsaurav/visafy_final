<?php
require_once '../config/db_connect.php';
header('Content-Type: application/json');

try {
    // Start transaction
    $conn->begin_transaction();

    // Insert new assessment
    $stmt = $conn->prepare("
        INSERT INTO user_assessments (user_id, start_time) 
        VALUES (?, NOW())
    ");
    
    // For anonymous users, use a placeholder user ID (you might want to adjust this based on your needs)
    $anonymous_user_id = 0; // or create a specific user ID for anonymous users
    $stmt->bind_param("i", $anonymous_user_id);
    $stmt->execute();
    
    $assessment_id = $conn->insert_id;
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'assessment_id' => $assessment_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_error === false) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 