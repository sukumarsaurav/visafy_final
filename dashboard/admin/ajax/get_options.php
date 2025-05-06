<?php
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if question_id is provided
if (!isset($_GET['question_id']) || !is_numeric($_GET['question_id'])) {
    echo json_encode([]);
    exit;
}

$question_id = intval($_GET['question_id']);

try {
    // Prepare the query to get options with next question text
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            q2.question_text as next_question_text
        FROM 
            decision_tree_options o
            LEFT JOIN decision_tree_questions q2 ON o.next_question_id = q2.id
        WHERE 
            o.question_id = ?
        ORDER BY 
            o.id ASC
    ");

    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $options = [];
    while ($row = $result->fetch_assoc()) {
        // Clean and format the data
        $options[] = [
            'id' => $row['id'],
            'question_id' => $row['question_id'],
            'option_text' => htmlspecialchars($row['option_text']),
            'next_question_id' => $row['next_question_id'],
            'next_question_text' => $row['next_question_text'] ? htmlspecialchars($row['next_question_text']) : null,
            'is_endpoint' => (bool)$row['is_endpoint'],
            'endpoint_result' => $row['endpoint_result'] ? htmlspecialchars($row['endpoint_result']) : null,
            'endpoint_eligible' => (bool)$row['endpoint_eligible']
        ];
    }
    
    echo json_encode($options);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error fetching options: ' . $e->getMessage()
    ]);
}
?>
