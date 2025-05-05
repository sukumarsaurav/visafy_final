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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_id']) && is_numeric($_POST['question_id'])) {
    $question_id = intval($_POST['question_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First delete all options for this question
        $stmt = $conn->prepare("DELETE FROM decision_tree_options WHERE question_id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->close();
        
        // Then delete the question
        $stmt = $conn->prepare("DELETE FROM decision_tree_questions WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $response = ['success' => true, 'message' => 'Question and all its options deleted successfully'];
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $response['message'] = 'Error deleting question: ' . $e->getMessage();
    }
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);
