<?php
require_once '../config/db_connect.php';
header('Content-Type: application/json');

try {
    // Validate question_id parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid question ID');
    }

    $question_id = intval($_GET['id']);

    // Prepare and execute query to get question
    $stmt = $conn->prepare("
        SELECT q.*, c.name as category_name 
        FROM decision_tree_questions q 
        LEFT JOIN decision_tree_categories c ON q.category_id = c.id 
        WHERE q.id = ? AND q.is_active = 1
    ");
    
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($question = $result->fetch_assoc()) {
        $question['success'] = true;
        echo json_encode($question);
    } else {
        throw new Exception('Question not found');
    }
    
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
