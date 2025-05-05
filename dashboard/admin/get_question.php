<?php
// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../config/db_connect.php';
require_once '../../../includes/functions.php';

// Check if user is logged in as admin
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $question_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT q.*, c.name as category_name, 
                           (SELECT COUNT(*) FROM decision_tree_options WHERE question_id = q.id) as options_count,
                           u.first_name, u.last_name,
                           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                           FROM decision_tree_questions q
                           LEFT JOIN decision_tree_categories c ON q.category_id = c.id
                           LEFT JOIN users u ON q.created_by = u.id
                           WHERE q.id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $question = $result->fetch_assoc();
        $response = $question;
        $response['success'] = true;
    } else {
        $response['message'] = 'Question not found';
    }
    $stmt->close();
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);
