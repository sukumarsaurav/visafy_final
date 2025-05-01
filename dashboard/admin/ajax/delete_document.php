<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set response header
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get JSON data from request
$json_data = json_decode(file_get_contents('php://input'), true);

if (!$json_data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Extract required data
$requirement_id = isset($json_data['requirement_id']) ? intval($json_data['requirement_id']) : 0;

// Validate requirement ID
if ($requirement_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid requirement ID is required']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Verify requirement exists
    $check_req_stmt = $conn->prepare("SELECT id, visa_type_id, document_type_id FROM visa_required_documents WHERE id = ?");
    $check_req_stmt->bind_param("i", $requirement_id);
    $check_req_stmt->execute();
    $req_result = $check_req_stmt->get_result();
    
    if ($req_result->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'Document requirement not found']);
        exit;
    }
    
    $req_data = $req_result->fetch_assoc();
    
    // Store document info for response
    $doc_query = "SELECT d.name, d.id 
                  FROM document_types d 
                  JOIN visa_required_documents r ON d.id = r.document_type_id 
                  WHERE r.id = ?";
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->bind_param("i", $requirement_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    $doc_data = $doc_result->fetch_assoc();
    
    // Delete requirement
    $delete_stmt = $conn->prepare("DELETE FROM visa_required_documents WHERE id = ?");
    $delete_stmt->bind_param("i", $requirement_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception("Failed to delete document requirement: " . $conn->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Document requirement deleted successfully',
        'deleted_requirement' => [
            'id' => $requirement_id,
            'document_id' => $doc_data['id'],
            'document_name' => $doc_data['name'],
            'visa_type_id' => $req_data['visa_type_id']
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 