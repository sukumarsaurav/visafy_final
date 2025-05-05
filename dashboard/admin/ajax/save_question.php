<?php
require_once '../../../config/db_connect.php';
require_once '../../../includes/functions.php';

// Check if user is logged in as admin - MODIFY THIS SECTION
// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    $question_text = trim($_POST['question_text'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $user_id = $_SESSION['id'];
    
    // Validate form data
    if (empty($question_text)) {
        $response['message'] = 'Question text is required';
    } else {
        if ($question_id > 0) {
            // Update existing question
            $stmt = $conn->prepare("UPDATE decision_tree_questions 
                                 SET question_text = ?, description = ?, category_id = ?, is_active = ? 
                                 WHERE id = ?");
            $stmt->bind_param("ssiis", $question_text, $description, $category_id, $is_active, $question_id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Question updated successfully', 'question_id' => $question_id];
            } else {
                $response['message'] = 'Error updating question: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            // Insert new question
            $stmt = $conn->prepare("INSERT INTO decision_tree_questions 
                                 (question_text, description, category_id, is_active, created_by) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiii", $question_text, $description, $category_id, $is_active, $user_id);
            
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                $response = ['success' => true, 'message' => 'Question added successfully', 'question_id' => $new_id];
            } else {
                $response['message'] = 'Error adding question: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);