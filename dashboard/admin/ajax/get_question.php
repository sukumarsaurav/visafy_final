<?php
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid question ID'
    ]);
    exit;
}

$question_id = intval($_GET['id']);

try {
    // Get question details with joined category name and options count
    // Note the CONCAT for creator's full name (first_name + last_name)
    $sql = "SELECT q.*, 
            c.name as category_name,
            (SELECT COUNT(*) FROM decision_tree_options WHERE question_id = q.id) as options_count,
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name
            FROM decision_tree_questions q
            LEFT JOIN decision_tree_categories c ON q.category_id = c.id
            LEFT JOIN users u ON q.created_by = u.id
            WHERE q.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Question not found'
        ]);
        exit;
    }
    
    $question = $result->fetch_assoc();
    
    // Return success response with question data
    echo json_encode(array_merge(['success' => true], $question));
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
