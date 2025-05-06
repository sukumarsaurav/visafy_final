<?php
require_once '../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Get JSON data from request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate required fields
    if (!isset($data['assessment_id']) || !isset($data['question_id']) || !isset($data['option_id'])) {
        throw new Exception('Missing required fields');
    }

    // Start transaction
    $conn->begin_transaction();

    // Save the answer
    $stmt = $conn->prepare("
        INSERT INTO user_assessment_answers (assessment_id, question_id, option_id, answer_time)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("iii", 
        $data['assessment_id'],
        $data['question_id'],
        $data['option_id']
    );
    $stmt->execute();

    // Check if this option is an endpoint
    $stmt = $conn->prepare("
        SELECT is_endpoint, endpoint_eligible, endpoint_result 
        FROM decision_tree_options 
        WHERE id = ?
    ");
    
    $stmt->bind_param("i", $data['option_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $option = $result->fetch_assoc();

    if ($option['is_endpoint']) {
        // Update assessment with result
        $stmt = $conn->prepare("
            UPDATE user_assessments 
            SET is_complete = 1,
                end_time = NOW(),
                result_eligible = ?,
                result_text = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param("isi", 
            $option['endpoint_eligible'],
            $option['endpoint_result'],
            $data['assessment_id']
        );
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'is_endpoint' => (bool)$option['is_endpoint'],
        'endpoint_eligible' => $option['is_endpoint'] ? (bool)$option['endpoint_eligible'] : null,
        'endpoint_result' => $option['is_endpoint'] ? $option['endpoint_result'] : null
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

// Close database connection
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
