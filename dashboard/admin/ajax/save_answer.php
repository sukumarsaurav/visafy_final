<?php
require_once '../../../config/db_connect.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['assessment_id']) || !isset($data['question_id']) || !isset($data['option_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO user_assessment_answers (assessment_id, question_id, option_id, answer_time) 
                           VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iii", $data['assessment_id'], $data['question_id'], $data['option_id']);
    $stmt->execute();
    
    // Check if this is an endpoint option
    $stmt = $conn->prepare("
        SELECT is_endpoint, endpoint_eligible 
        FROM decision_tree_options 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $data['option_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // If endpoint, update assessment completion
    if ($result['is_endpoint']) {
        $stmt = $conn->prepare("
            UPDATE user_assessments 
            SET is_complete = 1, 
                end_time = NOW(), 
                result_eligible = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $result['endpoint_eligible'], $data['assessment_id']);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error saving answer: ' . $e->getMessage()
    ]);
}
?>
