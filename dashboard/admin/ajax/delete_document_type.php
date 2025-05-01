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

// Get document ID
$document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;

// Validate document ID
if ($document_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid document ID']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if document exists
    $check_stmt = $conn->prepare("SELECT id FROM document_types WHERE id = ?");
    $check_stmt->bind_param("i", $document_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Document type not found']);
        exit;
    }
    
    // Check if document is used in visa requirements
    $used_stmt = $conn->prepare("SELECT id FROM visa_required_documents WHERE document_type_id = ? LIMIT 1");
    $used_stmt->bind_param("i", $document_id);
    $used_stmt->execute();
    $used_result = $used_stmt->get_result();
    
    if ($used_result->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This document type is currently in use and cannot be deleted. Consider marking it as inactive instead.']);
        exit;
    }
    
    // Delete document
    $stmt = $conn->prepare("DELETE FROM document_types WHERE id = ?");
    $stmt->bind_param("i", $document_id);
    
    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to delete document type: ' . $conn->error]);
        exit;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Document type deleted successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
