<?php
require_once '../config/db_connect.php';
header('Content-Type: application/json');

try {
    // Validate question_id parameter
    if (!isset($_GET['question_id']) || !is_numeric($_GET['question_id'])) {
        throw new Exception('Invalid question ID');
    }

    $question_id = intval($_GET['question_id']);

    // Prepare and execute query to get options
    $stmt = $conn->prepare("
        SELECT id, option_text, is_endpoint, next_question_id, endpoint_eligible, endpoint_result
        FROM decision_tree_options 
        WHERE question_id = ?
        ORDER BY id ASC
    ");
    
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $options = [];
    while ($row = $result->fetch_assoc()) {
        $options[] = [
            'id' => $row['id'],
            'option_text' => $row['option_text'],
            'is_endpoint' => (bool)$row['is_endpoint'],
            'next_question_id' => $row['next_question_id'],
            'endpoint_eligible' => (bool)$row['endpoint_eligible'],
            'endpoint_result' => $row['endpoint_result']
        ];
    }
    
    echo json_encode($options);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
