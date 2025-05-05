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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $option_id = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
    $question_id = intval($_POST['question_id'] ?? 0);
    $option_text = trim($_POST['option_text'] ?? '');
    $is_endpoint = isset($_POST['is_endpoint']) ? 1 : 0;
    
    // If it's an endpoint, get endpoint data, otherwise get next question
    if ($is_endpoint) {
        $next_question_id = null;
        $endpoint_result = trim($_POST['endpoint_result'] ?? '');
        $endpoint_eligible = isset($_POST['endpoint_eligible']) ? 1 : 0;
    } else {
        $next_question_id = !empty($_POST['next_question_id']) ? intval($_POST['next_question_id']) : null;
        $endpoint_result = null;
        $endpoint_eligible = null;
    }
    
    // Validate form data
    if (empty($option_text)) {
        $response['message'] = 'Option text is required';
    } elseif ($question_id <= 0) {
        $response['message'] = 'Invalid question ID';
    } elseif (!$is_endpoint && empty($next_question_id)) {
        $response['message'] = 'Next question is required for non-endpoint options';
    } else {
        if ($option_id > 0) {
            // Update existing option
            $stmt = $conn->prepare("UPDATE decision_tree_options 
                                   SET option_text = ?, next_question_id = ?, is_endpoint = ?, 
                                   endpoint_result = ?, endpoint_eligible = ?
                                   WHERE id = ?");
            $stmt->bind_param("ssissi", $option_text, $next_question_id, $is_endpoint, 
                            $endpoint_result, $endpoint_eligible, $option_id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Option updated successfully', 'option_id' => $option_id];
            } else {
                $response['message'] = 'Error updating option: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            // Insert new option
            $stmt = $conn->prepare("INSERT INTO decision_tree_options 
                                   (question_id, option_text, next_question_id, is_endpoint, 
                                   endpoint_result, endpoint_eligible) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isisis", $question_id, $option_text, $next_question_id, 
                            $is_endpoint, $endpoint_result, $endpoint_eligible);
            
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                $response = ['success' => true, 'message' => 'Option added successfully', 'option_id' => $new_id];
            } else {
                $response['message'] = 'Error adding option: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);
